<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Persistence\Doctrine;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use Doctrine\DBAL\Connection;

final readonly class BlockedPeriodRepository implements BlockedPeriodRepositoryInterface
{
    public function __construct(private Connection $availabilityConnection)
    {
    }

    public function add(BlockedPeriod $period): void
    {
        $this->availabilityConnection->insert('blocked_period', [
            'id' => $period->id,
            'room_id' => $period->roomId,
            'check_in' => $period->period->checkIn->format('Y-m-d'),
            'check_out' => $period->period->checkOut->format('Y-m-d'),
            'created_at' => $period->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?BlockedPeriod
    {
        /** @var array{id: string, room_id: string, check_in: string, check_out: string, created_at: string}|false $row */
        $row = $this->availabilityConnection->fetchAssociative(
            'SELECT id, room_id, check_in, check_out, created_at FROM blocked_period WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function remove(string $id): void
    {
        $this->availabilityConnection->delete('blocked_period', ['id' => $id]);
    }

    public function hasOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        $count = $this->availabilityConnection->fetchOne(
            'SELECT COUNT(*) FROM blocked_period
             WHERE room_id = :roomId
               AND check_in < :checkOut
               AND check_out > :checkIn',
            [
                'roomId' => $roomId,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
            ],
        );

        return $count > 0;
    }

    /** @return list<BlockedPeriod> */
    public function listByRoomId(string $roomId): array
    {
        /** @var list<array{id: string, room_id: string, check_in: string, check_out: string, created_at: string}> $rows */
        $rows = $this->availabilityConnection->fetchAllAssociative(
            'SELECT id, room_id, check_in, check_out, created_at FROM blocked_period
             WHERE room_id = :roomId
             ORDER BY check_in ASC',
            ['roomId' => $roomId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function removeByRoomAndPeriod(
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void {
        $this->availabilityConnection->executeStatement(
            'DELETE FROM blocked_period WHERE room_id = :roomId AND check_in = :checkIn AND check_out = :checkOut',
            [
                'roomId' => $roomId,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
            ],
        );
    }

    /**
     * @param array{id: string, room_id: string, check_in: string, check_out: string, created_at: string} $row
     */
    private function hydrate(array $row): BlockedPeriod
    {
        return new BlockedPeriod(
            $row['id'],
            $row['room_id'],
            new DatePeriod(
                new \DateTimeImmutable($row['check_in']),
                new \DateTimeImmutable($row['check_out']),
            ),
            new \DateTimeImmutable($row['created_at']),
        );
    }
}
