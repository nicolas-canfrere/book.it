<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\ValueObject\GuestId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class ReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private Connection $reservationConnection)
    {
    }

    public function add(Reservation $reservation): void
    {
        $this->reservationConnection->insert('reservation', [
            'id' => $reservation->id->value,
            'room_id' => $reservation->roomId->value,
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
        $connection = $this->reservationConnection;
        $connection->transactional(static function () use ($connection, $reservation): void {
            $connection->update('reservation', [
                'status' => $reservation->status->value,
                'actual_departure_date' => $reservation->actualDepartureDate?->format('Y-m-d'),
                'cancelled_at' => $reservation->cancelledAt?->format('Y-m-d'),
                'cancelled_by' => $reservation->cancelledBy,
            ], ['id' => $reservation->id->value]);

            $connection->delete('guest', ['reservation_id' => $reservation->id->value]);

            foreach ($reservation->guests as $guest) {
                $connection->insert('guest', [
                    'id' => $guest->id->value,
                    'reservation_id' => $reservation->id->value,
                    'first_name' => $guest->firstName,
                    'last_name' => $guest->lastName,
                    'date_of_birth' => $guest->dateOfBirth->format('Y-m-d'),
                ]);
            }
        });
    }

    public function get(ReservationId $id): ?Reservation
    {
        /** @var list<array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string, actual_departure_date: string|null, cancelled_at: string|null, cancelled_by: string|null, g_id: string|null, first_name: string|null, last_name: string|null, date_of_birth: string|null}> $rows */
        $rows = $this->reservationConnection->fetchAllAssociative(
            'SELECT r.id, r.room_id, r.booker_id, r.check_in, r.check_out, r.total_price, r.guest_count,
                    r.cancellation_terms_days_threshold, r.price_breakdown, r.status, r.created_at,
                    r.actual_departure_date, r.cancelled_at, r.cancelled_by,
                    rg.id AS g_id, rg.first_name, rg.last_name, rg.date_of_birth
               FROM reservation r
               LEFT JOIN guest rg ON rg.reservation_id = r.id
              WHERE r.id = :id
              ORDER BY rg.id',
            ['id' => $id->value],
        );

        if ([] === $rows) {
            return null;
        }

        $reservation = $this->hydrate($rows[0]);

        $reservation->guests = array_values(array_filter(array_map(
            function (array $row): ?Guest {
                if (null === $row['g_id']) {
                    return null;
                }

                return new Guest(
                    id: new GuestId($row['g_id']),
                    firstName: (string) $row['first_name'],
                    lastName: (string) $row['last_name'],
                    dateOfBirth: new \DateTimeImmutable((string) $row['date_of_birth']),
                );
            },
            $rows,
        )));

        return $reservation;
    }

    public function listByBooker(string $bookerId, int $page, int $limit): ReservationPage
    {
        $count = $this->reservationConnection->fetchOne(
            'SELECT COUNT(*) FROM reservation WHERE booker_id = :bookerId',
            ['bookerId' => $bookerId],
        );
        $total = is_numeric($count) ? (int) $count : 0;

        if (0 === $total) {
            return new ReservationPage([], 0);
        }

        $offset = ($page - 1) * $limit;

        /** @var list<array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string, actual_departure_date: string|null, cancelled_at: string|null, cancelled_by: string|null, g_id: string|null, first_name: string|null, last_name: string|null, date_of_birth: string|null}> $rows */
        $rows = $this->reservationConnection->fetchAllAssociative(
            'SELECT r.id, r.room_id, r.booker_id, r.check_in, r.check_out, r.total_price, r.guest_count,
                    r.cancellation_terms_days_threshold, r.price_breakdown, r.status, r.created_at,
                    r.actual_departure_date, r.cancelled_at, r.cancelled_by,
                    rg.id AS g_id, rg.first_name, rg.last_name, rg.date_of_birth
               FROM reservation r
               LEFT JOIN guest rg ON rg.reservation_id = r.id
              WHERE r.id IN (
                  SELECT id FROM reservation
                   WHERE booker_id = :bookerId
                   ORDER BY created_at DESC
                   LIMIT :limit OFFSET :offset
              )
              ORDER BY r.created_at DESC, r.id, rg.id',
            ['bookerId' => $bookerId, 'limit' => $limit, 'offset' => $offset],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        );

        $byId = [];
        $guestsByReservationId = [];
        foreach ($rows as $row) {
            $rid = $row['id'];
            if (!isset($byId[$rid])) {
                $byId[$rid] = $row;
                $guestsByReservationId[$rid] = [];
            }
            if (null !== $row['g_id']) {
                $guestsByReservationId[$rid][] = $row;
            }
        }

        $reservations = [];
        foreach ($byId as $rid => $row) {
            $reservation = $this->hydrate($row);
            $reservation->guests = array_map(
                fn(array $g) => new Guest(
                    id: new GuestId($g['g_id']),
                    firstName: (string) $g['first_name'],
                    lastName: (string) $g['last_name'],
                    dateOfBirth: new \DateTimeImmutable((string) $g['date_of_birth']),
                ),
                $guestsByReservationId[$rid],
            );
            $reservations[] = $reservation;
        }

        return new ReservationPage($reservations, $total);
    }

    /**
     * @param array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string, actual_departure_date: string|null, cancelled_at: string|null, cancelled_by: string|null} $row
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
            id: new ReservationId($row['id']),
            roomId: new RoomId($row['room_id']),
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
        $reservation->actualDepartureDate = null !== $row['actual_departure_date']
            ? new \DateTimeImmutable($row['actual_departure_date'])
            : null;
        $reservation->cancelledAt = null !== $row['cancelled_at']
            ? new \DateTimeImmutable($row['cancelled_at'])
            : null;
        $reservation->cancelledBy = $row['cancelled_by'];

        return $reservation;
    }
}
