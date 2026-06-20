<?php

declare(strict_types=1);

namespace App\Tests\Geo\Infrastructure\Contract;

use App\Geo\Infrastructure\Contract\DbalGeoPlaceChecker;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DbalGeoPlaceCheckerTest extends TestCase
{
    #[Test]
    public function itReturnsTrueWhenGeoPlaceExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('FROM geo_place'),
                ['id' => '2988507'],
            )
            ->willReturn(1);

        $checker = new DbalGeoPlaceChecker($connection);

        self::assertTrue($checker->exists(new GeoPlaceId('2988507')));
    }

    #[Test]
    public function itReturnsFalseWhenGeoPlaceIsMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(0);

        $checker = new DbalGeoPlaceChecker($connection);

        self::assertFalse($checker->exists(new GeoPlaceId('9999999')));
    }
}
