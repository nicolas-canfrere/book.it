<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class PriceBreakdown
{
    /** @param list<NightPrice> $nights */
    public function __construct(
        public array $nights,
    ) {
    }

    /**
     * @param list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(
                static fn (array $n) => new NightPrice(
                    $n['date'],
                    $n['rateAmountCents'],
                    $n['discountPercent'],
                    $n['effectiveAmountCents'],
                ),
                $data,
            ),
        );
    }

    /**
     * @return list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (NightPrice $n) => [
                'date' => $n->date,
                'rateAmountCents' => $n->rateAmountCents,
                'discountPercent' => $n->discountPercent,
                'effectiveAmountCents' => $n->effectiveAmountCents,
            ],
            $this->nights,
        );
    }
}
