<?php

declare(strict_types=1);

namespace App\Shared\AI\ValueObject;

final readonly class ModelQualityStats
{
    public function __construct(
        public int $accepted,
        public int $rejected,
        public float $acceptanceRate,
    ) {
    }
}
