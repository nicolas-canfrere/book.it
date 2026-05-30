<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\PreRegisterGuests;

use OpenApi\Attributes as OA;
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
        #[OA\Property(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                    new OA\Property(property: 'dateOfBirth', type: 'string', format: 'date', example: '1990-01-15'),
                ],
                type: 'object',
            ),
        )]
        public readonly array $guests = [],
    ) {
    }
}
