<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use Doctrine\DBAL\Connection;

final readonly class ReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function add(Reservation $reservation): void
    {
        $this->bookit->insert('reservation', [
            'id' => $reservation->id,
            'room_id' => $reservation->roomId,
            'booker_id' => $reservation->bookerId,
            'check_in' => $reservation->period->checkIn->format('Y-m-d'),
            'check_out' => $reservation->period->checkOut->format('Y-m-d'),
            'total_price' => $reservation->totalPrice,
            'cancellation_terms_days_threshold' => $reservation->cancellationTerms->daysThreshold,
            'price_breakdown' => json_encode($reservation->priceBreakdown->toArray()) ?: '[]',
            'status' => $reservation->status->value,
            'created_at' => $reservation->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Reservation
    {
        /** @var array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, room_id, booker_id, check_in, check_out, total_price,
                    cancellation_terms_days_threshold, price_breakdown, status, created_at
               FROM reservation
              WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string} $row
     */
    private function hydrate(array $row): Reservation
    {
        $threshold = $row['cancellation_terms_days_threshold'];
        $cancellationTerms = null !== $threshold
            ? CancellationTerms::withThreshold((int) $threshold)
            : CancellationTerms::alwaysRefundable();

        /** @var list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}> $nights */
        $nights = json_decode($row['price_breakdown'], true);
        $priceBreakdown = PriceBreakdown::fromArray($nights);

        $reservation = new Reservation(
            id: $row['id'],
            roomId: $row['room_id'],
            bookerId: $row['booker_id'],
            period: new DatePeriod(
                new \DateTimeImmutable($row['check_in']),
                new \DateTimeImmutable($row['check_out']),
            ),
            totalPrice: (int) $row['total_price'],
            cancellationTerms: $cancellationTerms,
            priceBreakdown: $priceBreakdown,
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
        $reservation->status = ReservationStatus::from($row['status']);

        return $reservation;
    }
}
