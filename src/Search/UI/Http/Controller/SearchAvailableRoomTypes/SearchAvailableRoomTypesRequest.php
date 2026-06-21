<?php

declare(strict_types=1);

namespace App\Search\UI\Http\Controller\SearchAvailableRoomTypes;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback([SearchAvailableRoomTypesRequest::class, 'validateDateRange'])]
final readonly class SearchAvailableRoomTypesRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public ?string $geoPlaceId = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public ?string $city = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        public ?string $checkOut = null,
        #[Assert\NotBlank]
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(20)]
        public ?int $guests = null,
    ) {
    }

    public static function validateDateRange(self $request, ExecutionContextInterface $context): void
    {
        if (null === $request->checkIn || null === $request->checkOut) {
            return;
        }

        if (new \DateTimeImmutable($request->checkOut) <= new \DateTimeImmutable($request->checkIn)) {
            $context->buildViolation('checkOut must be after checkIn.')
                ->atPath('checkOut')
                ->addViolation();
        }
    }
}
