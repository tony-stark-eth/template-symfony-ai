<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Service;

use App\Shared\AI\Service\ModelQualityTracker;
use App\Shared\AI\ValueObject\ModelQualityStats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(ModelQualityTracker::class)]
final class ModelQualityTrackerTest extends TestCase
{
    private ModelQualityTracker $tracker;

    protected function setUp(): void
    {
        $this->tracker = new ModelQualityTracker(new ArrayAdapter());
    }

    public function testInitialStatsAreZero(): void
    {
        $stats = $this->tracker->getStats('some-model');

        self::assertSame(0, $stats->accepted);
        self::assertSame(0, $stats->rejected);
        self::assertSame(0.0, $stats->acceptanceRate);
    }

    public function testRecordAcceptanceAndRejection(): void
    {
        $this->tracker->recordAcceptance('model-a');
        $this->tracker->recordAcceptance('model-a');
        $this->tracker->recordRejection('model-a');

        $stats = $this->tracker->getStats('model-a');

        self::assertSame(2, $stats->accepted);
        self::assertSame(1, $stats->rejected);
        // L42: exact 4-decimal rounding — 2/3 = 0.66666... → round to 0.6667
        // round(..., 3) would give 0.667, round(..., 5) would give 0.66667
        self::assertSame(0.6667, $stats->acceptanceRate);
    }

    public function testStatsPersistAcrossReads(): void
    {
        // L72/L105: verify cache->save is called (kills MethodCallRemoval on expiresAfter/save)
        $cache = new ArrayAdapter();
        $tracker = new ModelQualityTracker($cache);

        $tracker->recordAcceptance('persisted-model');

        // Create a new tracker instance from the same cache to verify persistence
        $tracker2 = new ModelQualityTracker($cache);
        $stats = $tracker2->getStats('persisted-model');

        self::assertSame(1, $stats->accepted);
        self::assertSame(0, $stats->rejected);
    }

    public function testGetAllStats(): void
    {
        $this->tracker->recordAcceptance('model-x');
        $this->tracker->recordRejection('model-y');

        $all = $this->tracker->getAllStats();

        self::assertCount(2, $all);
        self::assertTrue($all->containsKey('model-x'));
        self::assertTrue($all->containsKey('model-y'));
        self::assertContainsOnlyInstancesOf(ModelQualityStats::class, $all->toArray());
    }

    public function testGetAllStatsPersistsIndex(): void
    {
        // L105: verify index is persisted (kills MethodCallRemoval on addToIndex save)
        $cache = new ArrayAdapter();
        $tracker = new ModelQualityTracker($cache);

        $tracker->recordAcceptance('indexed-model');

        // New tracker from same cache should see the model in getAllStats
        $tracker2 = new ModelQualityTracker($cache);
        $all = $tracker2->getAllStats();

        self::assertCount(1, $all);
        self::assertTrue($all->containsKey('indexed-model'));
    }
}
