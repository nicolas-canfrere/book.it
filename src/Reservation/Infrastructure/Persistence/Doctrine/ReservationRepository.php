<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
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
            'guest_count' => $reservation->guestCount->value,
            'cancellation_terms_days_threshold' => $reservation->cancellationTerms->daysThreshold,
            'price_breakdown' => json_encode($reservation->priceBreakdown->toArray()) ?: '[]',
            'status' => $reservation->status->value,
            'created_at' => $reservation->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function save(Reservation $reservation): void
    {
        $connection = $this->bookit;
        $connection->transactional(static function () use ($connection, $reservation): void {
            $connection->update('reservation', ['status' => $reservation->status->value], ['id' => $reservation->id]);

            $connection->delete('reservation_guest', ['reservation_id' => $reservation->id]);

            foreach ($reservation->guests as $guest) {
                $connection->insert('reservation_guest', [
                    'id' => $guest->id,
                    'reservation_id' => $reservation->id,
                    'first_name' => $guest->firstName,
                    'last_name' => $guest->lastName,
                    'date_of_birth' => $guest->dateOfBirth->format('Y-m-d'),
                ]);
            }
        });
    }

    public function get(string $id): ?Reservation
    {
        /** @var array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, room_id, booker_id, check_in, check_out, total_price, guest_count,
                    cancellation_terms_days_threshold, price_breakdown, status, created_at
               FROM reservation
              WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        $reservation = $this->hydrate($row);

        /** @var list<array{id: string, first_name: string, last_name: string, date_of_birth: string}> $guestRows */
        $guestRows = $this->bookit->fetchAllAssociative(
            'SELECT id, first_name, last_name, date_of_birth
               FROM reservation_guest
              WHERE reservation_id = :id
              ORDER BY id',
            ['id' => $id],
        );

        $reservation->guests = array_map(
            fn(array $g) => new Guest(
                id: $g['id'],
                firstName: $g['first_name'],
                lastName: $g['last_name'],
                dateOfBirth: new \DateTimeImmutable($g['date_of_birth']),
            ),
            $guestRows,
        );

        return $reservation;
    }

    /**
     * @param array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string} $row
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
            guestCount: new GuestCount((int) $row['guest_count']),
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
        $reservation->status = ReservationStatus::from($row['status']);

        return $reservation;
    }
}
