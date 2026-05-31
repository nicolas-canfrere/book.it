<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine;

use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineBaseRateRepository implements BaseRateRepositoryInterface
{
    public function __construct(private Connection $pricingConnection)
    {
    }

    public function save(BaseRate $baseRate): void
    {
        $this->pricingConnection->executeStatement(
            'INSERT INTO base_rate (room_id, amount_cents, updated_at)
             VALUES (:roomId, :amountCents, :updatedAt)
             ON CONFLICT (room_id) DO UPDATE SET amount_cents = :amountCents, updated_at = :updatedAt',
            [
                'roomId' => $baseRate->roomId,
                'amountCents' => $baseRate->amountCents,
                'updatedAt' => $baseRate->updatedAt->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function findByRoomId(string $roomId): ?BaseRate
    {
        /** @var array{room_id: string, amount_cents: int, updated_at: string}|false $row */
        $row = $this->pricingConnection->fetchAssociative(
            'SELECT room_id, amount_cents, updated_at FROM base_rate WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array{room_id: string, amount_cents: int, updated_at: string} $row
     */
    private function hydrate(array $row): BaseRate
    {
        return new BaseRate(
            $row['room_id'],
            $row['amount_cents'],
            new \DateTimeImmutable($row['updated_at']),
        );
    }
}
