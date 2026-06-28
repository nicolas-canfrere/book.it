<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommandHandler;
use App\Hotel\Domain\Exception\InvalidGeoPlaceException;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Port\GeoPlaceCheckerInterface;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Event\HotelRegistered;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use App\Tests\Fake\FakeEventDispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterHotelCommandHandlerTest extends TestCase
{
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        $this->tenantContext = new TenantContext();
        $this->tenantContext->set(new OrganizationId('00000000-0000-0000-0000-000000000001'));
    }

    #[Test]
    public function itDispatchesHotelRegisteredOnSuccess(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker, $this->tenantContext);
        ($handler)(new RegisterHotelCommand(
            id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'),
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelRegistered::class, $event);
        self::assertSame('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $event->hotelId);
        self::assertSame('Le Grand Hôtel', $event->name);
        self::assertSame('Paris', $event->city);
        self::assertSame('FR', $event->country);
        self::assertNull($event->starRating);
    }

    #[Test]
    public function itDispatchesStarRatingWhenProvided(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker, $this->tenantContext);
        ($handler)(new RegisterHotelCommand(
            id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a12'),
            name: 'Luxury Palace',
            address: new Address('5 avenue Foch', '75016', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            starRating: new StarRating(4, false),
        ));

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelRegistered::class, $event);
        self::assertSame(4, $event->starRating);
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelAlreadyExists(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(true);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker, $this->tenantContext);

        try {
            ($handler)(new RegisterHotelCommand(
                id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a13'),
                name: 'Le Grand Hôtel',
                address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
                createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            ));
            self::fail('Expected HotelAlreadyExistsException was not thrown');
        } catch (\App\Hotel\Domain\Exception\HotelAlreadyExistsException) {
            // expected
        }

        self::assertEmpty($dispatcher->getDispatched());
    }

    #[Test]
    public function itRegistersAHotelWithAValidGeoPlaceId(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);
        $geoPlaceChecker->method('exists')->willReturn(true);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker, $this->tenantContext);
        ($handler)(new RegisterHotelCommand(
            id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a14'),
            name: 'Hotel Ibis Paris',
            address: new Address('15 rue de Rivoli', '75001', 'Paris', 'FR', new GeoPlaceId('2988507')),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));

        self::assertInstanceOf(HotelRegistered::class, $dispatcher->getLastDispatched());
    }

    #[Test]
    public function itRejectsAnUnknownGeoPlaceId(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);
        $geoPlaceChecker->method('exists')->willReturn(false);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker, $this->tenantContext);

        try {
            ($handler)(new RegisterHotelCommand(
                id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a15'),
                name: 'Hotel Ibis Paris',
                address: new Address('15 rue de Rivoli', '75001', 'Paris', 'FR', new GeoPlaceId('9999999')),
                createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            ));
            self::fail('Expected InvalidGeoPlaceException was not thrown');
        } catch (InvalidGeoPlaceException) {
            // expected
        }

        self::assertEmpty($dispatcher->getDispatched());
    }
}
