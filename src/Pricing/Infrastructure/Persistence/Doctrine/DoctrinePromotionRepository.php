<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use Doctrine\DBAL\Connection;

final readonly class DoctrinePromotionRepository implements PromotionRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function save(Promotion $promotion): void
    {
        $this->bookit->executeStatement(
            'INSERT INTO pricing_promotion (id, room_id, check_in, check_out, discount_percent, created_at, updated_at)
             VALUES (:id, :roomId, :checkIn, :checkOut, :discountPercent, :createdAt, :updatedAt)
             ON CONFLICT (id) DO UPDATE SET check_in = :checkIn, check_out = :checkOut, discount_percent = :discountPercent, updated_at = :updatedAt',
            [
                'id' => $promotion->id,
                'roomId' => $promotion->roomId,
                'checkIn' => $promotion->getCheckIn()->format('Y-m-d'),
                'checkOut' => $promotion->getCheckOut()->format('Y-m-d'),
                'discountPercent' => $promotion->getDiscountPercent(),
                'createdAt' => $promotion->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $promotion->getUpdatedAt()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function findById(string $id): ?Promotion
    {
        /** @var array{id: string, room_id: string, check_in: string, check_out: string, discount_percent: int, created_at: string, updated_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, room_id, check_in, check_out, discount_percent, created_at, updated_at FROM pricing_promotion WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /** @return list<Promotion> */
    public function findByRoomId(string $roomId): array
    {
        /** @var list<array{id: string, room_id: string, check_in: string, check_out: string, discount_percent: int, created_at: string, updated_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            'SELECT id, room_id, check_in, check_out, discount_percent, created_at, updated_at FROM pricing_promotion
             WHERE room_id = :roomId
             ORDER BY check_in ASC',
            ['roomId' => $roomId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function hasOverlap(string $roomId, DatePeriod $period, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM pricing_promotion
                WHERE room_id = :roomId
                  AND check_in < :checkOut
                  AND check_out > :checkIn';

        $params = [
            'roomId' => $roomId,
            'checkIn' => $period->checkIn->format('Y-m-d'),
            'checkOut' => $period->checkOut->format('Y-m-d'),
        ];

        if (null !== $excludeId) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = $excludeId;
        }

        $count = $this->bookit->fetchOne($sql, $params);

        return $count > 0;
    }

    public function delete(Promotion $promotion): void
    {
        $this->bookit->delete('pricing_promotion', ['id' => $promotion->id]);
    }

    /**
     * @param array{id: string, room_id: string, check_in: string, check_out: string, discount_percent: int, created_at: string, updated_at: string} $row
     */
    private function hydrate(array $row): Promotion
    {
        return new Promotion(
            id: $row['id'],
            roomId: $row['room_id'],
            checkIn: new \DateTimeImmutable($row['check_in']),
            checkOut: new \DateTimeImmutable($row['check_out']),
            discountPercent: (int) $row['discount_percent'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }
}
