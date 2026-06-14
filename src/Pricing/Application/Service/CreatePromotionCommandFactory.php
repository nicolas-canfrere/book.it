<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\UseCase\CreatePromotion\CreatePromotionCommand;
use App\Pricing\Domain\Port\PromotionIdGeneratorInterface;
use App\Shared\Domain\ValueObject\RoomId;
use Psr\Clock\ClockInterface;

final readonly class CreatePromotionCommandFactory
{
    public function __construct(
        private PromotionIdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $roomId, string $checkIn, string $checkOut, int $discountPercent): CreatePromotionCommand
    {
        return new CreatePromotionCommand(
            id: $this->idGenerator->generate(),
            roomId: new RoomId($roomId),
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            discountPercent: $discountPercent,
            createdAt: $this->clock->now(),
        );
    }
}
