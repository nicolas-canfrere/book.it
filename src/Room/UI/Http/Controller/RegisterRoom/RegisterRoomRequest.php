<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\RegisterRoom;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRoomRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 50)]
        #[OA\Property(type: 'string', example: '101', maxLength: 50, minLength: 1)]
        public ?string $number = null,
    ) {
    }
}
