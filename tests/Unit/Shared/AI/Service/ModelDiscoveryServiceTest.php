<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Service;

use App\Shared\AI\Service\ModelDiscoveryService;
use App\Shared\AI\ValueObject\CircuitBreakerState;
use App\Shared\AI\ValueObject\ModelId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(ModelDiscoveryService::class)]
final class ModelDiscoveryServiceTest extends TestCase
{
    private ArrayAdapter $cache;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->clock = new MockClock();
    }

    public function testDiscoversFreeModels(): void
    {
        $client = new MockHttpClient(new MockResponse($this->makeApiResponse([
            [
                'id' => 'free-model-1',
                'context_length' => 32768,
                'prompt' => '0',
                'completion' => '0',
            ],
            [
                'id' => 'paid-model',
                'context_length' => 32768,
                'prompt' => '0.001',
                'completion' => '0.002',
            ],
            [
                'id' => 'free-small',
                'context_length' => 4096,
                'prompt' => '0',
                'completion' => '0',
            ],
            [
                'id' => 'free-model-2',
                'context_length' => 16384,
                'prompt' => '0',
                'completion' => '0',
            ],
        ])));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with('Discovered {count} free OpenRouter models', self::callback(
                static fn (array $ctx): bool => $ctx['count'] === 2,
            ));

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $models = $service->discoverFreeModels();

        self::assertCount(2, $models);
        $values = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        self::assertContains('free-model-1', $values);
        self::assertContains('free-model-2', $values);
        self::assertNotContains('paid-model', $values);
        self::assertNotContains('free-small', $values);
    }

    public function testFilterBlockedModels(): void
    {
        $client = new MockHttpClient(new MockResponse($this->makeApiResponse([
            [
                'id' => 'good-model',
                'context_length' => 32768,
                'prompt' => '0',
                'completion' => '0',
            ],
            [
                'id' => 'blocked-model',
                'context_length' => 32768,
                'prompt' => '0',
                'completion' => '0',
            ],
        ])));

        $service = new ModelDiscoveryService(
            $client,
            $this->cache,
            $this->clock,
            $this->createMock(LoggerInterface::class),
            'blocked-model',
        );

        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('good-model', $first->value);
    }

    public function testCachesResultsAndResetsBreakerOnSuccess(): void
    {
        $callCount = 0;
        $body = $this->makeApiResponse([
            [
                'id' => 'cached-model',
                'context_length' => 32768,
                'prompt' => '0',
                'completion' => '0',
            ],
        ]);

        $factory = static function () use (&$callCount, $body): MockResponse {
            ++$callCount;

            return new MockResponse($body);
        };

        $client = new MockHttpClient($factory);
        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $this->createMock(LoggerInterface::class));

        $service->discoverFreeModels();
        $service->discoverFreeModels();

        self::assertSame(1, $callCount);

        // Verify cache was populated
        $cacheItem = $this->cache->getItem('openrouter_free_models');
        self::assertTrue($cacheItem->isHit());
        /** @var list<string> $cached */
        $cached = $cacheItem->get();
        self::assertSame(['cached-model'], $cached);

        // Verify breaker was reset (key deleted)
        $breakerItem = $this->cache->getItem('openrouter_cb');
        self::assertFalse($breakerItem->isHit());
    }

    public function testCircuitBreakerOpensAfterThreeFailures(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'timeout',
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(4))->method('warning');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);

        $service->discoverFreeModels(); // failure 1
        $service->discoverFreeModels(); // failure 2
        $service->discoverFreeModels(); // failure 3 → opens breaker

        // Verify breaker is open
        $breakerItem = $this->cache->getItem('openrouter_cb');
        self::assertTrue($breakerItem->isHit());
        /** @var array{state: string, failures: int, opened_at: ?int} $breakerData */
        $breakerData = $breakerItem->get();
        self::assertSame(CircuitBreakerState::Open->value, $breakerData['state']);
        self::assertSame(3, $breakerData['failures']);
        self::assertNotNull($breakerData['opened_at']);
    }

    public function testCircuitBreakerOpenReturnsCachedModels(): void
    {
        // Pre-populate cache with models
        $cacheItem = $this->cache->getItem('openrouter_free_models');
        $cacheItem->set(['cached-fallback']);
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);

        // Open the breaker
        $breakerItem = $this->cache->getItem('openrouter_cb');
        $breakerItem->set([
            'state' => CircuitBreakerState::Open->value,
            'failures' => 3,
            'opened_at' => $this->clock->now()->getTimestamp(),
        ]);
        $breakerItem->expiresAfter(172800);
        $this->cache->save($breakerItem);

        // API should NOT be called
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::never())->method('request');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with('Circuit breaker open, using cached model list');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('cached-fallback', $first->value);
    }

    public function testCircuitBreakerTransitionsToHalfOpenAfterResetPeriod(): void
    {
        // Open the breaker in the past (beyond reset period)
        $openedAt = $this->clock->now()->getTimestamp() - 86401; // 1 second past reset
        $breakerItem = $this->cache->getItem('openrouter_cb');
        $breakerItem->set([
            'state' => CircuitBreakerState::Open->value,
            'failures' => 3,
            'opened_at' => $openedAt,
        ]);
        $breakerItem->expiresAfter(172800);
        $this->cache->save($breakerItem);

        // API should be called (half-open probe)
        $body = $this->makeApiResponse([
            [
                'id' => 'recovered-model',
                'context_length' => 32768,
                'prompt' => '0',
                'completion' => '0',
            ],
        ]);
        $client = new MockHttpClient(new MockResponse($body));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with('Circuit breaker half-open, attempting probe request');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('recovered-model', $first->value);
    }

    public function testHalfOpenProbeFailureOpensBreaker(): void
    {
        // Open the breaker in the past (beyond reset period) → will transition to HalfOpen
        $openedAt = $this->clock->now()->getTimestamp() - 86401;
        $breakerItem = $this->cache->getItem('openrouter_cb');
        $breakerItem->set([
            'state' => CircuitBreakerState::Open->value,
            'failures' => 3,
            'opened_at' => $openedAt,
        ]);
        $breakerItem->expiresAfter(172800);
        $this->cache->save($breakerItem);

        // Probe fails
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'still down',
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        // debug for half-open + warning for failure + warning for breaker open
        $logger->expects(self::once())->method('debug');
        $logger->expects(self::exactly(2))->method('warning');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $service->discoverFreeModels();

        // Verify breaker is back to open
        $breakerItem = $this->cache->getItem('openrouter_cb');
        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $breakerItem->get();
        self::assertSame(CircuitBreakerState::Open->value, $data['state']);
    }

    public function testFailureLogsCountAndThreshold(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'timeout',
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                'Model discovery failed ({count}/{threshold}): {error}',
                self::callback(static fn (array $ctx): bool => $ctx['count'] === 1
                    && $ctx['threshold'] === 3
                    && str_contains((string) $ctx['error'], 'timeout')),
            );

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $service->discoverFreeModels();
    }

    public function testOpenBreakerLogsResetDuration(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'timeout',
        ]));

        $warningCalls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning')
            ->willReturnCallback(static function (string $msg, array $ctx) use (&$warningCalls): void {
                $warningCalls[] = [
                    'msg' => $msg,
                    'ctx' => $ctx,
                ];
            });

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);

        // 3 failures to open the breaker
        $service->discoverFreeModels();
        $service->discoverFreeModels();
        $service->discoverFreeModels();

        // Last warning should be the "opened" message with seconds
        $lastCall = end($warningCalls);
        self::assertIsArray($lastCall);
        self::assertSame('Circuit breaker opened for model discovery ({seconds}s)', $lastCall['msg']);
        self::assertSame(86400, $lastCall['ctx']['seconds']);
    }

    public function testEmptyCacheReturnsEmptyCollection(): void
    {
        // Open breaker, no cache → should return empty
        $breakerItem = $this->cache->getItem('openrouter_cb');
        $breakerItem->set([
            'state' => CircuitBreakerState::Open->value,
            'failures' => 3,
            'opened_at' => $this->clock->now()->getTimestamp(),
        ]);
        $breakerItem->expiresAfter(172800);
        $this->cache->save($breakerItem);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::never())->method('request');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $this->createMock(LoggerInterface::class));
        $models = $service->discoverFreeModels();

        self::assertTrue($models->isEmpty());
    }

    /**
     * @param list<array{id: string, context_length: int, prompt: string, completion: string}> $models
     */
    private function makeApiResponse(array $models): string
    {
        $data = array_map(static fn (array $m): array => [
            'id' => $m['id'],
            'context_length' => $m['context_length'],
            'pricing' => [
                'prompt' => $m['prompt'],
                'completion' => $m['completion'],
            ],
        ], $models);

        return json_encode(['data' => $data], JSON_THROW_ON_ERROR);
    }
}
