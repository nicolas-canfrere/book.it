<?php

declare(strict_types=1);

namespace App\Booker\UI\Http\Controller;

use App\Booker\Domain\Model\Booker;

final class BookerSerializer
{
    /**
     * @return array{id: string, firstName: string, lastName: string, email: string, phone: string, dateOfBirth: string, registeredAt: string}
     */
    public function serialize(Booker $booker): array
    {
        return [
            'id' => $booker->id,
            'firstName' => $booker->firstName,
            'lastName' => $booker->lastName,
            'email' => $booker->email,
            'phone' => $booker->phone,
            'dateOfBirth' => $booker->dateOfBirth->format('Y-m-d'),
            'registeredAt' => $booker->registeredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
