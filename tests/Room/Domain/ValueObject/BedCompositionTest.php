<?php

declare(strict_types=1);

namespace App\Tests\Room\Domain\ValueObject;

use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\BedEntry;
use App\Room\Domain\ValueObject\BedType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BedCompositionTest extends TestCase
{
    #[Test]
    public function itRejectsEmptyList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BedComposition([]);
    }

    #[Test]
    public function itRejectsBedCountBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BedEntry(BedType::King, 0);
    }

    #[Test]
    public function itRejectsBedCountAboveTen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BedEntry(BedType::King, 11);
    }

    #[Test]
    public function itSerializesToArray(): void
    {
        $composition = new BedComposition([
            new BedEntry(BedType::King, 1),
            new BedEntry(BedType::SofaBed, 1),
        ]);

        self::assertSame(
            [['type' => 'king', 'count' => 1], ['type' => 'sofa_bed', 'count' => 1]],
            $composition->toArray(),
        );
    }

    #[Test]
    public function itDeserializesFromArray(): void
    {
        $data = [['type' => 'queen', 'count' => 2]];
        $composition = BedComposition::fromArray($data);

        self::assertCount(1, $composition->entries);
        self::assertSame(BedType::Queen, $composition->entries[0]->type);
        self::assertSame(2, $composition->entries[0]->count);
    }

    #[Test]
    public function itRoundTrips(): void
    {
        $original = new BedComposition([new BedEntry(BedType::Single, 2)]);
        $roundTripped = BedComposition::fromArray($original->toArray());

        self::assertSame($original->toArray(), $roundTripped->toArray());
    }
}
