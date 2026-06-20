<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\Service;

use App\Hotel\Application\Service\RegisterHotelCommandFactory;
use App\Hotel\Domain\Port\HotelIdGeneratorInterface;
use App\Shared\Domain\ValueObject\HotelId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

#[Group('unit')]
final class RegisterHotelCommandFactoryTest extends TestCase
{
    private RegisterHotelCommandFactory $factory;

    protected function setUp(): void
    {
        $idGenerator = $this->createStub(HotelIdGeneratorInterface::class);
        $idGenerator->method('generate')->willReturn(new HotelId(Uuid::v7()->toRfc4122()));

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable());

        $this->factory = new RegisterHotelCommandFactory($idGenerator, $clock);
    }

    #[Test]
    public function itThrowsWhenAnyFieldIsNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create(
            name: null,
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );
    }

    #[Test]
    public function itBuildsAddressWithGeoPlaceIdWhenProvided(): void
    {
        $command = $this->factory->create(
            name: 'Hotel Ibis Paris',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
            geoPlaceId: '2988507',
        );

        self::assertNotNull($command->address->geoPlaceId);
        self::assertSame('2988507', $command->address->geoPlaceId->value);
    }

    #[Test]
    public function itBuildsAddressWithoutGeoPlaceIdWhenNull(): void
    {
        $command = $this->factory->create(
            name: 'Hotel Ibis Paris',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        self::assertNull($command->address->geoPlaceId);
    }
}
