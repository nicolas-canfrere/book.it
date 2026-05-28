<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\PreRegisterGuests;

use Symfony\Component\Validator\Constraints as Assert;

final class PreRegisterGuestsRequest
{
    /**
     * @param list<array{firstName: string, lastName: string, dateOfBirth: string}> $guests
     */
    public function __construct(
        #[Assert\All([
            new Assert\Collection([
                'firstName' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
                'lastName' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
                'dateOfBirth' => [new Assert\NotBlank(), new Assert\Date()],
            ]),
        ])]
        public readonly array $guests = [],
    ) {
    }
}
