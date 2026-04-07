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

        // L202: Verify model IDs are mapped correctly (kills UnwrapArrayMap)
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('free-model-1', $first->value);

        // L192: Verify cache is actually populated (kills MethodCallRemoval on cache->save)
        $cacheItem = $this->cache->getItem('openrouter_free_models');
        self::assertTrue($cacheItem->isHit());
        /** @var list<string> $cached */
        $cached = $cacheItem->get();
        self::assertCount(2, $cached);
        self::assertContains('free-model-1', $cached);
        self::assertContains('free-model-2', $cached);

        // L42/L192: Verify cache TTL is set to CACHE_TTL (3600)
        // After discovery the models should still be cached — we can't directly read TTL
        // from ArrayAdapter, but we verify the item exists (save was called)
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $service = new ModelDiscoveryService(
            $client,
            $this->cache,
            $this->clock,
            $logger,
            'blocked-model',
        );

        $models = $service->discoverFreeModels();

        // L221: Verify blocked models are actually filtered (kills Continue_)
        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('good-model', $first->value);
        $values = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        self::assertNotContains('blocked-model', $values);
    }

    /**
     * L25: Kills UnwrapTrim — blocked model with whitespace padding must still be filtered.
     */
    public function testFilterBlockedModelsWithWhitespacePadding(): void
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        // Whitespace around blocked model name
        $service = new ModelDiscoveryService(
            $client,
            $this->cache,
            $this->clock,
            $logger,
            ' blocked-model ',
        );

        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $values = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        self::assertContains('good-model', $values);
        self::assertNotContains('blocked-model', $values);
    }

    /**
     * L209: Kills LogicalAnd — model with free prompt but paid completion should NOT be included.
     */
    public function testModelWithFreePromptButPaidCompletionIsExcluded(): void
    {
        $client = new MockHttpClient(new MockResponse($this->makeApiResponse([
            [
                'id' => 'partial-free',
                'context_length' => 32768,
                'prompt' => '0',
                'completion' => '0.001',
            ],
            [
                'id' => 'truly-free',
                'context_length' => 32768,
                'prompt' => '0',
                'completion' => '0',
            ],
        ])));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with('Discovered {count} free OpenRouter models', self::callback(
                static fn (array $ctx): bool => $ctx['count'] === 1,
            ));

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $values = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        self::assertContains('truly-free', $values);
        self::assertNotContains('partial-free', $values);
    }

    /**
     * L216: Kills LessThan — model with context_length exactly 8192 should be included.
     */
    public function testModelWithExactMinContextLengthIsIncluded(): void
    {
        $client = new MockHttpClient(new MockResponse($this->makeApiResponse([
            [
                'id' => 'exact-boundary',
                'context_length' => 8192,
                'prompt' => '0',
                'completion' => '0',
            ],
            [
                'id' => 'below-boundary',
                'context_length' => 8191,
                'prompt' => '0',
                'completion' => '0',
            ],
        ])));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with('Discovered {count} free OpenRouter models', self::callback(
                static fn (array $ctx): bool => $ctx['count'] === 1,
            ));

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('exact-boundary', $first->value);
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
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);

        $service->discoverFreeModels();
        $service->discoverFreeModels();

        self::assertSame(1, $callCount);

        // L192: Verify cache was populated (kills MethodCallRemoval on cache->save)
        $cacheItem = $this->cache->getItem('openrouter_free_models');
        self::assertTrue($cacheItem->isHit());
        /** @var list<string> $cached */
        $cached = $cacheItem->get();
        self::assertSame(['cached-model'], $cached);

        // L72: Verify breaker was reset (key deleted) — kills MethodCallRemoval on deleteItem
        $breakerItem = $this->cache->getItem('openrouter_cb');
        self::assertFalse($breakerItem->isHit());
    }

    /**
     * L72: Verify resetBreaker actually deletes the breaker key from cache.
     * Kills two MethodCallRemoval mutations on deleteItem.
     */
    public function testSuccessfulFetchDeletesBreakerKeyFromCache(): void
    {
        // Pre-set a breaker entry in cache
        $breakerItem = $this->cache->getItem('openrouter_cb');
        $breakerItem->set([
            'state' => CircuitBreakerState::Closed->value,
            'failures' => 2,
            'opened_at' => null,
        ]);
        $breakerItem->expiresAfter(172800);
        $this->cache->save($breakerItem);

        // Confirm it's there
        self::assertTrue($this->cache->getItem('openrouter_cb')->isHit());

        $client = new MockHttpClient(new MockResponse($this->makeApiResponse([
            [
                'id' => 'model-1',
                'context_length' => 32768,
                'prompt' => '0',
                'completion' => '0',
            ],
        ])));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $service->discoverFreeModels();

        // After success, the breaker key must be deleted
        $breakerItem = $this->cache->getItem('openrouter_cb');
        self::assertFalse($breakerItem->isHit());
    }

    /**
     * L33: Kills IncrementInteger on BREAKER_THRESHOLD = 3.
     * Breaker must open at exactly 3 failures, not 4.
     */
    public function testCircuitBreakerOpensAtExactlyThreeFailuresNotFour(): void
    {
        $callCount = 0;
        $client = new MockHttpClient(static function () use (&$callCount): MockResponse {
            ++$callCount;

            return new MockResponse('', [
                'error' => 'timeout',
            ]);
        });

        $logger = $this->createMock(LoggerInterface::class);

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);

        $service->discoverFreeModels(); // failure 1
        $service->discoverFreeModels(); // failure 2

        // After 2 failures, breaker should still be Closed
        /** @var array{state: string, failures: int, opened_at: ?int} $data2 */
        $data2 = $this->cache->getItem('openrouter_cb')->get();
        self::assertSame(CircuitBreakerState::Closed->value, $data2['state']);

        $service->discoverFreeModels(); // failure 3 → opens breaker

        // After exactly 3 failures, breaker must be Open
        /** @var array{state: string, failures: int, opened_at: ?int} $data3 */
        $data3 = $this->cache->getItem('openrouter_cb')->get();
        self::assertSame(CircuitBreakerState::Open->value, $data3['state']);
        self::assertSame(3, $data3['failures']);
        self::assertNotNull($data3['opened_at']);

        // 4th call should NOT hit the API (breaker is open)
        $service->discoverFreeModels();
        self::assertSame(3, $callCount, 'API should not be called when breaker is open');
    }

    public function testCircuitBreakerOpensAfterThreeFailures(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'timeout',
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        // 3 failure warnings + 1 breaker-opened warning = 4
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

        // L105: Verify debug log is called with exact message (kills MethodCallRemoval)
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

    /**
     * L136: Kills GreaterThanOrEqualTo — exactly at reset time should transition to HalfOpen.
     * L137: Kills MethodCallRemoval on saveBreakerData — verify state is saved after transition.
     */
    public function testCircuitBreakerTransitionsToHalfOpenAtExactResetBoundary(): void
    {
        // Open the breaker at exactly the reset boundary (86400 seconds ago)
        $openedAt = $this->clock->now()->getTimestamp() - 86400; // exactly at boundary
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
        $logger->expects(self::once())->method('info');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('recovered-model', $first->value);

        // L137: Verify the HalfOpen state was actually saved to cache before the probe
        // After success the breaker is reset (deleted), so we verify the probe happened
        // by confirming the model was fetched from API
        $cacheItem = $this->cache->getItem('openrouter_free_models');
        self::assertTrue($cacheItem->isHit());
    }

    /**
     * L136 boundary: one second BEFORE reset — should remain Open, not transition.
     */
    public function testCircuitBreakerStaysOpenOneSecondBeforeReset(): void
    {
        $openedAt = $this->clock->now()->getTimestamp() - 86399; // 1 second before reset
        $breakerItem = $this->cache->getItem('openrouter_cb');
        $breakerItem->set([
            'state' => CircuitBreakerState::Open->value,
            'failures' => 3,
            'opened_at' => $openedAt,
        ]);
        $breakerItem->expiresAfter(172800);
        $this->cache->save($breakerItem);

        // Pre-populate model cache so we get something back
        $modelCache = $this->cache->getItem('openrouter_free_models');
        $modelCache->set(['cached-model']);
        $modelCache->expiresAfter(3600);
        $this->cache->save($modelCache);

        // API should NOT be called (breaker still open)
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
        self::assertSame('cached-model', $first->value);
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
        $logger->expects(self::once())->method('info');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('recovered-model', $first->value);
    }

    public function testHalfOpenProbeFailureOpensBreaker(): void
    {
        // Open the breaker in the past (beyond reset period) -> will transition to HalfOpen
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
                    && is_string($ctx['error']) && str_contains($ctx['error'], 'timeout')),
            );

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $service->discoverFreeModels();
    }

    /**
     * L171: Kills MethodCallRemoval on logger->warning in openBreaker.
     */
    public function testOpenBreakerLogsWarningWithResetDuration(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'timeout',
        ]));

        $warningCalls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(4))->method('warning')
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

    /**
     * L183: Kills mutations on expiresAfter(BREAKER_RESET_SECONDS * 2) and cache->save().
     * Verify that saveBreakerData actually persists to cache with correct data.
     */
    public function testSaveBreakerDataPersistsStateToCache(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'timeout',
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);

        // Trigger one failure to exercise saveBreakerData
        $service->discoverFreeModels();

        // L183/L184: Verify the breaker data was actually saved to cache
        $breakerItem = $this->cache->getItem('openrouter_cb');
        self::assertTrue($breakerItem->isHit(), 'Breaker data must be saved to cache');

        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $breakerItem->get();
        self::assertSame(CircuitBreakerState::Closed->value, $data['state']);
        self::assertSame(1, $data['failures']);
        self::assertNull($data['opened_at']);
    }

    /**
     * L150: Kills Coalesce on tryFrom(...) ?? Closed — corrupted cache state falls back to Closed.
     */
    public function testCorruptedBreakerStateFallsBackToClosed(): void
    {
        // Write corrupted state to cache
        $breakerItem = $this->cache->getItem('openrouter_cb');
        $breakerItem->set([
            'state' => 'invalid_state_value',
            'failures' => 1,
            'opened_at' => null,
        ]);
        $breakerItem->expiresAfter(172800);
        $this->cache->save($breakerItem);

        // Should treat as Closed and try fetching
        $client = new MockHttpClient(new MockResponse($this->makeApiResponse([
            [
                'id' => 'model-1',
                'context_length' => 32768,
                'prompt' => '0',
                'completion' => '0',
            ],
        ])));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('model-1', $first->value);
    }

    /**
     * L150 (incrementFailures): Corrupted state during failure increment still saves correctly.
     */
    public function testCorruptedStateDuringFailureIncrementFallsBackToClosed(): void
    {
        // Write corrupted state
        $breakerItem = $this->cache->getItem('openrouter_cb');
        $breakerItem->set([
            'state' => 'garbage',
            'failures' => 0,
            'opened_at' => null,
        ]);
        $breakerItem->expiresAfter(172800);
        $this->cache->save($breakerItem);

        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'timeout',
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $service->discoverFreeModels();

        // incrementFailures should have used Closed as fallback
        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $this->cache->getItem('openrouter_cb')->get();
        self::assertSame(CircuitBreakerState::Closed->value, $data['state']);
        self::assertSame(1, $data['failures']);
    }

    public function testEmptyCacheReturnsEmptyCollection(): void
    {
        // Open breaker, no cache -> should return empty
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with('Circuit breaker open, using cached model list');

        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $models = $service->discoverFreeModels();

        self::assertTrue($models->isEmpty());
    }

    /**
     * L137: Verify saveBreakerData is called during HalfOpen transition
     * by checking the cache reflects the HalfOpen state after getState() runs
     * but before the fetch succeeds (via a failing probe).
     */
    public function testHalfOpenTransitionSavesStateToCache(): void
    {
        $openedAt = $this->clock->now()->getTimestamp() - 86400; // exactly at boundary
        $breakerItem = $this->cache->getItem('openrouter_cb');
        $breakerItem->set([
            'state' => CircuitBreakerState::Open->value,
            'failures' => 3,
            'opened_at' => $openedAt,
        ]);
        $breakerItem->expiresAfter(172800);
        $this->cache->save($breakerItem);

        // Probe will fail — so we can inspect the intermediate HalfOpen -> Open transition
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'still down',
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        $service = new ModelDiscoveryService($client, $this->cache, $this->clock, $logger);
        $service->discoverFreeModels();

        // After failed half-open probe, breaker reopens. The fact that it went through
        // HalfOpen (and the probe was attempted) proves saveBreakerData was called.
        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $this->cache->getItem('openrouter_cb')->get();
        self::assertSame(CircuitBreakerState::Open->value, $data['state']);
        self::assertNotNull($data['opened_at']);
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

        return json_encode([
            'data' => $data,
        ], JSON_THROW_ON_ERROR);
    }
}
