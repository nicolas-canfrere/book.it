<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\CreatePromotion;

use App\Pricing\Domain\Exception\PromotionOverlapException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Pricing\Domain\ValueObject\DiscountPercent;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class CreatePromotionCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private PromotionRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
    ) {
    }

    public function __invoke(CreatePromotionCommand $command): Promotion
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        $period = new DatePeriod(
            new \DateTimeImmutable($command->checkIn),
            new \DateTimeImmutable($command->checkOut),
        );

        if ($this->repository->hasOverlap($command->roomId, $period)) {
            throw new PromotionOverlapException();
        }

        $discountPercent = new DiscountPercent($command->discountPercent);

        $promotion = new Promotion(
            $command->id,
            $command->roomId,
            new \DateTimeImmutable($command->checkIn),
            new \DateTimeImmutable($command->checkOut),
            $discountPercent->value,
            $command->createdAt,
            $command->createdAt,
        );

        $this->repository->save($promotion);

        return $promotion;
    }
}
