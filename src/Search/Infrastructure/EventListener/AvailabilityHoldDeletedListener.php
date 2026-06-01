<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\AvailabilityHoldDeleted;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AvailabilityHoldDeleted::class)]
final readonly class AvailabilityHoldDeletedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(AvailabilityHoldDeleted $event): void
    {
        $this->connection->executeStatement(
            'DELETE FROM unavailable_periods WHERE source_id = :reservationId',
            ['reservationId' => $event->reservationId],
        );
    }
}
