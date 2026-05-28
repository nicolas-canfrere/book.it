<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommandHandler;
use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Domain\Event\ReservationCreated;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\GuestCapacityExceededException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\NightPrice;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;
use App\Tests\Fake\FakeAsyncCommandDispatcher;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Fake\FakeTransactionManager;
use App\Tests\Reservation\Infrastructure\FakeBookerExistenceChecker;
use App\Tests\Reservation\Infrastructure\FakeCancellationPolicyFetcher;
use App\Tests\Reservation\Infrastructure\FakePricingQuoteFetcher;
use App\Tests\Reservation\Infrastructure\FakeRoomAvailabilityChecker;
use App\Tests\Reservation\Infrastructure\FakeRoomCapacityFetcher;
use App\Tests\Reservation\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Reservation\Infrastructure\Persistence\InMemory\InMemoryReservationRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CreateReservationCommandHandlerTest extends TestCase
{
    private const string RESERVATION_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440001';
    private const string BOOKER_ID = '550e8400-e29b-41d4-a716-446655440002';

    private InMemoryReservationRepository $repository;
    private FakeRoomExistenceChecker $roomExists;
    private FakeBookerExistenceChecker $bookerExists;
    private FakeRoomAvailabilityChecker $availabilityChecker;
    private FakeRoomCapacityFetcher $roomCapacityFetcher;
    private FakePricingQuoteFetcher $pricingQuoteFetcher;
    private FakeCancellationPolicyFetcher $cancellationPolicyFetcher;
    private FakeEventDispatcher $eventDispatcher;
    private FakeTransactionManager $transactionManager;
    private FakeAsyncCommandDispatcher $asyncDispatcher;
    private CreateReservationCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryReservationRepository();
        $this->roomExists = new FakeRoomExistenceChecker();
        $this->bookerExists = new FakeBookerExistenceChecker();
        $this->availabilityChecker = new FakeRoomAvailabilityChecker();
        $this->roomCapacityFetcher = new FakeRoomCapacityFetcher();
        $this->pricingQuoteFetcher = new FakePricingQuoteFetcher();
        $this->cancellationPolicyFetcher = new FakeCancellationPolicyFetcher();
        $this->eventDispatcher = new FakeEventDispatcher();
        $this->transactionManager = new FakeTransactionManager();
        $this->asyncDispatcher = new FakeAsyncCommandDispatcher();

        $this->handler = new CreateReservationCommandHandler(
            $this->repository,
            $this->roomExists,
            $this->bookerExists,
            $this->availabilityChecker,
            $this->roomCapacityFetcher,
            $this->pricingQuoteFetcher,
            $this->cancellationPolicyFetcher,
            $this->eventDispatcher,
            $this->transactionManager,
            $this->asyncDispatcher,
        );
    }

    #[Test]
    public function itCreatesAReservationInPendingStateAndDispatchesEvent(): void
    {
        ($this->handler)($this->makeCommand());

        $reservation = $this->repository->get(self::RESERVATION_ID);
        self::assertNotNull($reservation);
        self::assertSame(self::RESERVATION_ID, $reservation->id);
        self::assertSame(self::ROOM_ID, $reservation->roomId);
        self::assertSame(self::BOOKER_ID, $reservation->bookerId);
        self::assertSame('2026-06-01', $reservation->period->checkIn->format('Y-m-d'));
        self::assertSame('2026-06-05', $reservation->period->checkOut->format('Y-m-d'));
        self::assertSame(42000, $reservation->totalPrice);
        self::assertSame(2, $reservation->guestCount->value);
        self::assertSame(ReservationStatus::Pending, $reservation->status);
        self::assertNull($reservation->cancellationTerms->daysThreshold);
        self::assertCount(4, $reservation->priceBreakdown->nights);
        self::assertSame('2026-06-01', $reservation->priceBreakdown->nights[0]->date);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(self::RESERVATION_ID, $event->reservationId);
        self::assertSame(self::ROOM_ID, $event->roomId);
        self::assertSame(self::BOOKER_ID, $event->bookerId);
        self::assertSame(42000, $event->totalPrice);
        self::assertNull($event->cancellationTerms->daysThreshold);
        self::assertCount(4, $event->priceBreakdown->nights);
    }

    #[Test]
    public function itStoresCancellationTermsWithThresholdWhenPolicyIsSet(): void
    {
        $this->cancellationPolicyFetcher->setTerms(CancellationTerms::withThreshold(7));

        ($this->handler)($this->makeCommand());

        $reservation = $this->repository->get(self::RESERVATION_ID);
        self::assertNotNull($reservation);
        self::assertSame(7, $reservation->cancellationTerms->daysThreshold);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(7, $event->cancellationTerms->daysThreshold);
    }

    #[Test]
    public function itStoresPriceBreakdownFromQuote(): void
    {
        $this->pricingQuoteFetcher->setSnapshot(new PricingQuoteSnapshot(
            19000,
            new PriceBreakdown([
                new NightPrice('2026-06-01', 10000, null, 10000),
                new NightPrice('2026-06-02', 10000, 10, 9000),
            ]),
        ));

        ($this->handler)($this->makeCommand());

        $reservation = $this->repository->get(self::RESERVATION_ID);
        self::assertNotNull($reservation);
        self::assertSame(19000, $reservation->totalPrice);
        self::assertCount(2, $reservation->priceBreakdown->nights);
        self::assertSame(10000, $reservation->priceBreakdown->nights[0]->effectiveAmountCents);
        self::assertSame(10, $reservation->priceBreakdown->nights[1]->discountPercent);
        self::assertSame(9000, $reservation->priceBreakdown->nights[1]->effectiveAmountCents);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(19000, $event->totalPrice);
        self::assertCount(2, $event->priceBreakdown->nights);
    }

    #[Test]
    public function itAcceptsZeroPrice(): void
    {
        $this->pricingQuoteFetcher->setSnapshot(
            new PricingQuoteSnapshot(0, new PriceBreakdown([])),
        );

        ($this->handler)($this->makeCommand());

        $reservation = $this->repository->get(self::RESERVATION_ID);
        self::assertNotNull($reservation);
        self::assertSame(0, $reservation->totalPrice);
    }

    #[Test]
    public function itThrowsWhenRoomDoesNotExist(): void
    {
        $this->roomExists->setExists(false);
        $this->expectException(RoomNotFoundException::class);

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenBookerDoesNotExist(): void
    {
        $this->bookerExists->setExists(false);
        $this->expectException(BookerNotFoundException::class);

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenRoomIsNotAvailable(): void
    {
        $this->availabilityChecker->setAvailable(false);
        $this->expectException(RoomNotAvailableException::class);

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenGuestCountExceedsRoomCapacity(): void
    {
        $this->roomCapacityFetcher->setCapacity(1);
        $this->expectException(GuestCapacityExceededException::class);

        ($this->handler)($this->makeCommand(guestCount: 2));
    }

    #[Test]
    public function itThrowsWhenRoomHasNoPricing(): void
    {
        $this->pricingQuoteFetcher->setSnapshot(null);
        $this->expectException(RoomNotBookableException::class);

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itDispatchesDelayedExpireCommandAfterCreation(): void
    {
        ($this->handler)($this->makeCommand());

        $dispatched = $this->asyncDispatcher->getLastDispatched();
        self::assertInstanceOf(ExpireReservationCommand::class, $dispatched);
        self::assertSame(self::RESERVATION_ID, $dispatched->reservationId);
    }

    private function makeCommand(string $id = self::RESERVATION_ID, int $guestCount = 2): CreateReservationCommand
    {
        return new CreateReservationCommand(
            id: $id,
            roomId: self::ROOM_ID,
            bookerId: self::BOOKER_ID,
            checkIn: new \DateTimeImmutable('2026-06-01'),
            checkOut: new \DateTimeImmutable('2026-06-05'),
            guestCount: $guestCount,
            createdAt: new \DateTimeImmutable('2026-05-18T10:00:00Z'),
        );
    }
}
