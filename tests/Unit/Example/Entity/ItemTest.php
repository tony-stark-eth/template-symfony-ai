<?php

declare(strict_types=1);

namespace App\Tests\Unit\Example\Entity;

use App\Example\Entity\Item;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Item::class)]
final class ItemTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $now = new \DateTimeImmutable('2025-01-15 12:00:00');
        $item = new Item('Test Item', 'A description', $now);

        self::assertNull($item->getId());
        self::assertSame('Test Item', $item->getName());
        self::assertSame('A description', $item->getDescription());
        self::assertSame($now, $item->getCreatedAt());
    }

    public function testNullableDescription(): void
    {
        $now = new \DateTimeImmutable('2025-01-15 12:00:00');
        $item = new Item('No Description', null, $now);

        self::assertNull($item->getDescription());
    }
}
