<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\GuestCapacityExceededException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\AvailableRoomPickerInterface;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Application\Transaction\TransactionManagerInterface;
use App\Shared\Domain\Event\ReservationCreated;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CreateReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private AvailableRoomPickerInterface $roomPicker,
        private BookerExistsInterface $bookerExists,
        private RoomCapacityFetcherInterface $roomCapacityFetcher,
        private PricingQuoteFetcherInterface $pricingQuoteFetcher,
        private CancellationPolicyFetcherInterface $cancellationPolicyFetcher,
        private EventDispatcherInterface $eventDispatcher,
        private TransactionManagerInterface $transactionManager,
        private AsyncCommandDispatcherInterface $asyncDispatcher,
    ) {
    }

    public function __invoke(CreateReservationCommand $command): void
    {
        $roomId = $this->roomPicker->pick($command->roomTypeId, $command->checkIn, $command->checkOut);

        if (null === $roomId) {
            throw new RoomNotAvailableException($command->roomTypeId);
        }

        if (!$this->bookerExists->exists($command->bookerId)) {
            throw new BookerNotFoundException($command->bookerId);
        }

        $capacity = $this->roomCapacityFetcher->fetchCapacity($roomId);
        if ($command->guestCount > $capacity) {
            throw new GuestCapacityExceededException($command->guestCount, $capacity);
        }

        $pricingQuote = $this->pricingQuoteFetcher->fetch($roomId, $command->checkIn, $command->checkOut);
        $cancellationTerms = $this->cancellationPolicyFetcher->fetch($roomId);

        $reservation = new Reservation(
            id: $command->id,
            roomId: $roomId,
            bookerId: $command->bookerId,
            period: new DatePeriod($command->checkIn, $command->checkOut),
            totalPrice: $pricingQuote->totalAmountCents,
            cancellationTerms: $cancellationTerms,
            priceBreakdown: $pricingQuote->breakdown,
            guestCount: new GuestCount($command->guestCount),
            createdAt: $command->createdAt,
        );

        $this->transactionManager->transactional(function () use ($reservation): void {
            $this->repository->add($reservation);

            $this->eventDispatcher->dispatch(new ReservationCreated(
                reservationId: $reservation->id,
                roomId: $reservation->roomId->value,
                bookerId: $reservation->bookerId,
                checkIn: $reservation->period->checkIn,
                checkOut: $reservation->period->checkOut,
                totalPrice: $reservation->totalPrice,
                cancellationTermsDaysThreshold: $reservation->cancellationTerms->daysThreshold,
                priceBreakdown: $reservation->priceBreakdown->toArray(),
            ));
        });

        $this->asyncDispatcher->dispatch(
            new ExpireReservationCommand($reservation->id),
            900_000,
        );
    }
}
