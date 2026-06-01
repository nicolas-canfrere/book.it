<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\BlockedPeriodDeleted;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BlockedPeriodDeleted::class)]
final readonly class BlockedPeriodDeletedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(BlockedPeriodDeleted $event): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            DELETE FROM unavailable_periods
            WHERE room_id = :roomId
              AND period = daterange(:checkIn, :checkOut)
            SQL,
            [
                'roomId' => $event->roomId,
                'checkIn' => $event->checkIn->format('Y-m-d'),
                'checkOut' => $event->checkOut->format('Y-m-d'),
            ],
        );
    }
}
