<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CreateReservation;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CreateReservationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[OA\Property(type: 'string', format: 'uuid')]
        public ?string $roomId = null,
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[OA\Property(type: 'string', format: 'uuid')]
        public ?string $bookerId = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2026-06-01')]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[Assert\GreaterThan(propertyPath: 'checkIn')]
        #[OA\Property(type: 'string', format: 'date', example: '2026-06-05')]
        public ?string $checkOut = null,
        #[Assert\NotBlank]
        #[Assert\Range(min: 1, max: 20)]
        #[OA\Property(type: 'integer', example: 2)]
        public ?int $guestCount = null,
    ) {
    }

    #[Assert\Callback]
    public function validateCheckInNotInPast(ExecutionContextInterface $context): void
    {
        if (null === $this->checkIn) {
            return;
        }

        $today = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d');
        if ($this->checkIn < $today) {
            $context->buildViolation('checkIn must be today or in the future (UTC).')
                ->atPath('checkIn')
                ->addViolation();
        }
    }
}
