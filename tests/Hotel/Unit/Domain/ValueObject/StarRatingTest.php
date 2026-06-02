<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Unit\Domain\ValueObject;

use App\Hotel\Domain\ValueObject\StarRating;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class StarRatingTest extends TestCase
{
    #[Test]
    public function itItCreatesABasicStarRating(): void
    {
        $rating = new StarRating(3, false);

        self::assertSame(3, $rating->stars);
        self::assertFalse($rating->superior);
    }

    #[Test]
    public function itItCreatesASuperiorStarRating(): void
    {
        $rating = new StarRating(4, true);

        self::assertSame(4, $rating->stars);
        self::assertTrue($rating->superior);
    }

    #[Test]
    public function itItRejectsStarsBelow1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stars must be between 1 and 5');

        new StarRating(0, false);
    }

    #[Test]
    public function itItRejectsStarsAbove5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stars must be between 1 and 5');

        new StarRating(6, false);
    }

    #[Test]
    public function itBoundary1StarIsValid(): void
    {
        $rating = new StarRating(1, false);
        self::assertSame(1, $rating->stars);
    }

    #[Test]
    public function itBoundary5StarsIsValid(): void
    {
        $rating = new StarRating(5, true);
        self::assertSame(5, $rating->stars);
    }
}
