<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchHotelStarRating\UpdateSearchHotelStarRatingCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\StarRatingClassified;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: StarRatingClassified::class)]
final readonly class StarRatingClassifiedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(StarRatingClassified $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchHotelStarRatingCommand(
            hotelId: $event->hotelId,
            starRating: $event->starRating,
        ));
    }
}
