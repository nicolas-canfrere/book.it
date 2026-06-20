<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Service;

use App\Geo\Application\Contract\GeoPlaceCheckerInterface as GeoPlaceCheckerContract;
use App\Hotel\Infrastructure\Service\GeoPlaceChecker;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GeoPlaceCheckerTest extends TestCase
{
    #[Test]
    public function itDelegatesToTheGeoPublishedContract(): void
    {
        $contract = $this->createStub(GeoPlaceCheckerContract::class);
        $contract->method('exists')->willReturn(true);

        $checker = new GeoPlaceChecker($contract);

        self::assertTrue($checker->exists(new GeoPlaceId('2988507')));
    }

    #[Test]
    public function itReturnsFalseWhenTheContractReportsNoMatch(): void
    {
        $contract = $this->createStub(GeoPlaceCheckerContract::class);
        $contract->method('exists')->willReturn(false);

        $checker = new GeoPlaceChecker($contract);

        self::assertFalse($checker->exists(new GeoPlaceId('0')));
    }
}
