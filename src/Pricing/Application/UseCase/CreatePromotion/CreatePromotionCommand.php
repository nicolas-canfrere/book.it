<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\CreatePromotion;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class CreatePromotionCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public RoomId $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $discountPercent,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
