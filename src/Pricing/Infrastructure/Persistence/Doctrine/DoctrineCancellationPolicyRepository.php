<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine;

use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineCancellationPolicyRepository implements CancellationPolicyRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function findByRoomId(string $roomId): ?CancellationPolicy
    {
        /** @var array{room_id: string, days_threshold: int, updated_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT room_id, days_threshold, updated_at
               FROM pricing_cancellation_policy
              WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(CancellationPolicy $policy): void
    {
        $this->bookit->executeStatement(
            'INSERT INTO pricing_cancellation_policy (room_id, days_threshold, updated_at)
             VALUES (:roomId, :daysThreshold, :updatedAt)
             ON CONFLICT (room_id) DO UPDATE
               SET days_threshold = :daysThreshold,
                   updated_at = :updatedAt',
            [
                'roomId' => $policy->roomId,
                'daysThreshold' => $policy->daysThreshold,
                'updatedAt' => $policy->updatedAt->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function deleteByRoomId(string $roomId): void
    {
        $this->bookit->delete('pricing_cancellation_policy', ['room_id' => $roomId]);
    }

    /**
     * @param array{room_id: string, days_threshold: int, updated_at: string} $row
     */
    private function hydrate(array $row): CancellationPolicy
    {
        return new CancellationPolicy(
            $row['room_id'],
            (int) $row['days_threshold'],
            new \DateTimeImmutable($row['updated_at']),
        );
    }
}
