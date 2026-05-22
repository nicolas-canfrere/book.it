<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Unit\Domain\ValueObject;

use App\Hotel\Domain\ValueObject\StarRating;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class StarRatingTest extends TestCase
{
    public function test_it_creates_a_basic_star_rating(): void
    {
        $rating = new StarRating(3, false);

        self::assertSame(3, $rating->stars);
        self::assertFalse($rating->superior);
    }

    public function test_it_creates_a_superior_star_rating(): void
    {
        $rating = new StarRating(4, true);

        self::assertSame(4, $rating->stars);
        self::assertTrue($rating->superior);
    }

    public function test_it_rejects_stars_below_1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stars must be between 1 and 5');

        new StarRating(0, false);
    }

    public function test_it_rejects_stars_above_5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stars must be between 1 and 5');

        new StarRating(6, false);
    }

    public function test_boundary_1_star_is_valid(): void
    {
        $rating = new StarRating(1, false);
        self::assertSame(1, $rating->stars);
    }

    public function test_boundary_5_stars_is_valid(): void
    {
        $rating = new StarRating(5, true);
        self::assertSame(5, $rating->stars);
    }
}
