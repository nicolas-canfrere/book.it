<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\GeoPlaceId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GeoPlaceIdTest extends TestCase
{
    #[Test]
    public function itExposesItsValueAsAString(): void
    {
        $id = new GeoPlaceId('2988507');

        self::assertSame('2988507', $id->value);
        self::assertSame('2988507', (string) $id);
    }

    #[Test]
    public function itComparesByValue(): void
    {
        self::assertTrue((new GeoPlaceId('2988507'))->equals(new GeoPlaceId('2988507')));
        self::assertFalse((new GeoPlaceId('2988507'))->equals(new GeoPlaceId('4717560')));
    }
}
