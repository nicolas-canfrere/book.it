<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentFailure;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class HandlePaymentFailureRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[SerializedName('reservation_id')]
        public string $reservationId = '',
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[SerializedName('event_id')]
        public string $eventId = '',
    ) {
    }
}
