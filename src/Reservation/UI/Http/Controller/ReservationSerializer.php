<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller;

use App\Reservation\Domain\Model\Reservation;

final readonly class ReservationSerializer
{
    /**
     * @return array{
     *     id: string,
     *     roomId: string,
     *     bookerId: string,
     *     checkIn: string,
     *     checkOut: string,
     *     totalPrice: int,
     *     status: string,
     *     cancellationTerms: array{daysThreshold: int|null},
     *     priceBreakdown: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>,
     *     createdAt: string
     * }
     */
    public function serialize(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'roomId' => $reservation->roomId,
            'bookerId' => $reservation->bookerId,
            'checkIn' => $reservation->period->checkIn->format('Y-m-d'),
            'checkOut' => $reservation->period->checkOut->format('Y-m-d'),
            'totalPrice' => $reservation->totalPrice,
            'status' => $reservation->status->value,
            'cancellationTerms' => [
                'daysThreshold' => $reservation->cancellationTerms->daysThreshold,
            ],
            'priceBreakdown' => $reservation->priceBreakdown->toArray(),
            'createdAt' => $reservation->createdAt
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
