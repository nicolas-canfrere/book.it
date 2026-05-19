<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommandHandler;
use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Domain\Event\ReservationCreated;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Tests\Fake\FakeAsyncCommandDispatcher;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Fake\FakeTransactionManager;
use App\Tests\Reservation\Infrastructure\FakeBookerExistenceChecker;
use App\Tests\Reservation\Infrastructure\FakeCancellationPolicyFetcher;
use App\Tests\Reservation\Infrastructure\FakePricingQuoteFetcher;
use App\Tests\Reservation\Infrastructure\FakeRoomAvailabilityChecker;
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
        self::assertSame(ReservationStatus::Pending, $reservation->status);
        self::assertNull($reservation->cancellationTerms->daysThreshold);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(self::RESERVATION_ID, $event->reservationId);
        self::assertSame(self::ROOM_ID, $event->roomId);
        self::assertSame(self::BOOKER_ID, $event->bookerId);
        self::assertSame(42000, $event->totalPrice);
        self::assertNull($event->cancellationTerms->daysThreshold);
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
    public function itAcceptsZeroPrice(): void
    {
        $this->pricingQuoteFetcher->setSnapshot(
            new \App\Reservation\Domain\ValueObject\PricingQuoteSnapshot(
                0,
                new \App\Reservation\Domain\ValueObject\PriceBreakdown([]),
            ),
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

    private function makeCommand(string $id = self::RESERVATION_ID): CreateReservationCommand
    {
        return new CreateReservationCommand(
            id: $id,
            roomId: self::ROOM_ID,
            bookerId: self::BOOKER_ID,
            checkIn: new \DateTimeImmutable('2026-06-01'),
            checkOut: new \DateTimeImmutable('2026-06-05'),
            createdAt: new \DateTimeImmutable('2026-05-18T10:00:00Z'),
        );
    }
}
