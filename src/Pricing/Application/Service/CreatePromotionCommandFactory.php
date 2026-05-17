<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\UseCase\CreatePromotion\CreatePromotionCommand;
use Psr\Clock\ClockInterface;

final readonly class CreatePromotionCommandFactory
{
    public function __construct(
        private IdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $roomId, string $checkIn, string $checkOut, int $discountPercent): CreatePromotionCommand
    {
        return new CreatePromotionCommand(
            id: $this->idGenerator->generate(),
            roomId: $roomId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            discountPercent: $discountPercent,
            createdAt: $this->clock->now(),
        );
    }
}
