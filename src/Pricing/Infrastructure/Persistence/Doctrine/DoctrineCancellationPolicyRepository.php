<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine;

use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Shared\Domain\ValueObject\RoomId;
use Doctrine\DBAL\Connection;

final readonly class DoctrineCancellationPolicyRepository implements CancellationPolicyRepositoryInterface
{
    public function __construct(private Connection $pricingConnection)
    {
    }

    public function findByRoomId(RoomId $roomId): ?CancellationPolicy
    {
        /** @var array{room_id: string, days_threshold: int, updated_at: string}|false $row */
        $row = $this->pricingConnection->fetchAssociative(
            'SELECT room_id, days_threshold, updated_at
               FROM cancellation_policy
              WHERE room_id = :roomId',
            ['roomId' => $roomId->value],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(CancellationPolicy $policy): void
    {
        $this->pricingConnection->executeStatement(
            'INSERT INTO cancellation_policy (room_id, days_threshold, updated_at)
             VALUES (:roomId, :daysThreshold, :updatedAt)
             ON CONFLICT (room_id) DO UPDATE
               SET days_threshold = :daysThreshold,
                   updated_at = :updatedAt',
            [
                'roomId' => $policy->roomId->value,
                'daysThreshold' => $policy->daysThreshold,
                'updatedAt' => $policy->updatedAt->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function deleteByRoomId(RoomId $roomId): void
    {
        $this->pricingConnection->delete('cancellation_policy', ['room_id' => $roomId->value]);
    }

    /**
     * @param array{room_id: string, days_threshold: int, updated_at: string} $row
     */
    private function hydrate(array $row): CancellationPolicy
    {
        return new CancellationPolicy(
            new RoomId($row['room_id']),
            $row['days_threshold'],
            new \DateTimeImmutable($row['updated_at']),
        );
    }
}
