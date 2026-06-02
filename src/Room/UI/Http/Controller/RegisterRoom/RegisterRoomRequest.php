<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\RegisterRoom;

use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRoomRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: RoomNumber::MAX_LENGTH)]
        #[OA\Property(type: 'string', example: '101', maxLength: RoomNumber::MAX_LENGTH, minLength: 1)]
        public ?string $number = null,
        #[Assert\NotNull]
        #[Assert\Range(min: RoomFloor::MIN_FLOOR, max: RoomFloor::MAX_FLOOR)]
        #[OA\Property(type: 'integer', example: 1, minimum: RoomFloor::MIN_FLOOR, maximum: RoomFloor::MAX_FLOOR, nullable: false)]
        public ?int $floor = null,
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [4])]
        #[OA\Property(type: 'string', format: 'uuid', example: '7f4d1234-0000-4000-8000-000000000001')]
        public ?string $roomTypeId = null,
    ) {
    }
}
