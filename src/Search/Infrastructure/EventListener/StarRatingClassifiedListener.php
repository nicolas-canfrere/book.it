<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\StarRatingClassified;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: StarRatingClassified::class)]
final readonly class StarRatingClassifiedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(StarRatingClassified $event): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET star_rating = :starRating WHERE hotel_id = :hotelId',
            ['starRating' => $event->starRating, 'hotelId' => $event->hotelId],
        );
    }
}
