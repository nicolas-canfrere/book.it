<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommandHandler;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\GuestCapacityExceededException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Port\AvailableRoomPickerInterface;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Application\Transaction\TransactionManagerInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class CreateReservationCommandHandlerTest extends TestCase
{
    private AvailableRoomPickerInterface&\PHPUnit\Framework\MockObject\MockObject $roomPicker;
    private BookerExistsInterface&\PHPUnit\Framework\MockObject\MockObject $bookerExists;
    private ReservationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;
    private PricingQuoteFetcherInterface&\PHPUnit\Framework\MockObject\MockObject $pricingQuoteFetcher;
    private CancellationPolicyFetcherInterface&\PHPUnit\Framework\MockObject\MockObject $cancellationPolicyFetcher;
    private EventDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject $eventDispatcher;
    private AsyncCommandDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject $asyncDispatcher;
    private CreateReservationCommandHandler $handler;

    private RoomId $resolvedRoomId;
    private RoomTypeId $roomTypeId;

    protected function setUp(): void
    {
        $this->resolvedRoomId = new RoomId('bbbbbbbb-0000-4000-8000-000000000001');
        $this->roomTypeId = new RoomTypeId('aaaaaaaa-0000-4000-8000-000000000001');

        $this->roomPicker = $this->createMock(AvailableRoomPickerInterface::class);
        $this->bookerExists = $this->createMock(BookerExistsInterface::class);
        $this->repository = $this->createMock(ReservationRepositoryInterface::class);

        $roomCapacityFetcher = $this->createMock(\App\Reservation\Domain\Port\RoomCapacityFetcherInterface::class);
        $roomCapacityFetcher->method('fetchCapacity')->willReturn(4);

        $this->pricingQuoteFetcher = $this->createMock(PricingQuoteFetcherInterface::class);
        $this->pricingQuoteFetcher->method('fetch')->willReturn(new PricingQuoteSnapshot(10000, new PriceBreakdown([])));

        $this->cancellationPolicyFetcher = $this->createMock(CancellationPolicyFetcherInterface::class);
        $this->cancellationPolicyFetcher->method('fetch')->willReturn(CancellationTerms::alwaysRefundable());

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->asyncDispatcher = $this->createMock(AsyncCommandDispatcherInterface::class);

        $transactionManager = $this->createMock(TransactionManagerInterface::class);
        $transactionManager->method('transactional')->willReturnCallback(static fn(\Closure $fn) => $fn());

        $this->handler = new CreateReservationCommandHandler(
            repository: $this->repository,
            roomPicker: $this->roomPicker,
            bookerExists: $this->bookerExists,
            roomCapacityFetcher: $roomCapacityFetcher,
            pricingQuoteFetcher: $this->pricingQuoteFetcher,
            cancellationPolicyFetcher: $this->cancellationPolicyFetcher,
            eventDispatcher: $this->eventDispatcher,
            transactionManager: $transactionManager,
            asyncDispatcher: $this->asyncDispatcher,
        );
    }

    private function makeCommand(int $guestCount = 2): CreateReservationCommand
    {
        return new CreateReservationCommand(
            id: 'cccccccc-0000-4000-8000-000000000001',
            roomTypeId: $this->roomTypeId,
            bookerId: 'dddddddd-0000-4000-8000-000000000001',
            checkIn: new \DateTimeImmutable('2026-08-01'),
            checkOut: new \DateTimeImmutable('2026-08-05'),
            guestCount: $guestCount,
            createdAt: new \DateTimeImmutable(),
        );
    }

    #[Test]
    public function itCreatesReservationSuccessfully(): void
    {
        $this->roomPicker->method('pick')->willReturn($this->resolvedRoomId);
        $this->bookerExists->method('exists')->willReturn(true);
        $this->repository->expects($this->once())->method('add');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->asyncDispatcher->expects($this->once())->method('dispatch');

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenNoRoomAvailable(): void
    {
        $this->roomPicker->method('pick')->willReturn(null);

        $this->expectException(RoomNotAvailableException::class);
        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenBookerDoesNotExist(): void
    {
        $this->roomPicker->method('pick')->willReturn($this->resolvedRoomId);
        $this->bookerExists->method('exists')->willReturn(false);

        $this->expectException(BookerNotFoundException::class);
        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenGuestCountExceedsCapacity(): void
    {
        $this->roomPicker->method('pick')->willReturn($this->resolvedRoomId);
        $this->bookerExists->method('exists')->willReturn(true);

        $this->expectException(GuestCapacityExceededException::class);
        ($this->handler)($this->makeCommand(guestCount: 99));
    }
}
