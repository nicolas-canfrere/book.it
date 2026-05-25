<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\RegisterRoomType;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRoomTypeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Suite Royale', maxLength: 100, minLength: 1)]
        public ?string $name = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 20)]
        #[OA\Property(type: 'integer', example: 2, minimum: 1, maximum: 20, nullable: false)]
        public ?int $livingSpaceCount = null,

        #[Assert\Range(min: 1, max: 2000)]
        #[OA\Property(type: 'integer', example: 80, minimum: 1, maximum: 2000, nullable: true)]
        public ?int $surfaceM2 = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 20)]
        #[OA\Property(type: 'integer', example: 2, minimum: 1, maximum: 20, nullable: false)]
        public ?int $guestCapacity = null,

        #[Assert\NotNull]
        #[OA\Property(type: 'boolean', example: false, nullable: false)]
        public ?bool $isAccessible = null,

        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\Collection([
                'type' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(choices: ['single', 'double', 'queen', 'king', 'bunk', 'sofa_bed', 'baby_cot']),
                ],
                'count' => [
                    new Assert\NotNull(),
                    new Assert\Type('integer'),
                    new Assert\Range(min: 1, max: 10),
                ],
            ]),
        ])]
        #[OA\Property(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['single', 'double', 'queen', 'king', 'bunk', 'sofa_bed', 'baby_cot']),
                    new OA\Property(property: 'count', type: 'integer', minimum: 1, maximum: 10),
                ],
                type: 'object',
            ),
        )]
        public ?array $bedComposition = null,
    ) {
    }
}
