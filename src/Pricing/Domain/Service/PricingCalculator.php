<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Service;

use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Pricing\Domain\ValueObject\NightPricingDetail;
use App\Pricing\Domain\ValueObject\PricingQuote;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class PricingCalculator implements PricingQuoteCalculatorInterface
{
    public function __construct(
        private BaseRateRepositoryInterface $baseRateRepository,
        private RatePeriodRepositoryInterface $ratePeriodRepository,
        private PromotionRepositoryInterface $promotionRepository,
    ) {
    }

    public function calculate(
        RoomId $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): PricingQuote {
        $baseRate = $this->baseRateRepository->findByRoomId($roomId);
        if (null === $baseRate) {
            throw new RoomHasNoBaseRateException($roomId);
        }
        $stayPeriod = new DatePeriod($checkIn, $checkOut);
        $ratePeriods = $this->ratePeriodRepository->findOverlappingByRoomId($roomId, $stayPeriod);
        $promotions = $this->promotionRepository->findOverlappingByRoomId($roomId, $stayPeriod);

        $nights = [];
        $total = 0;
        $current = $checkIn;

        while ($current < $checkOut) {
            $rateAmountCents = $baseRate->amountCents;
            foreach ($ratePeriods as $period) {
                if ($period->checkIn <= $current && $current < $period->checkOut) {
                    $rateAmountCents = $period->amountCents;
                    break;
                }
            }

            $discountPercent = null;
            foreach ($promotions as $promotion) {
                if ($promotion->getCheckIn() <= $current && $current < $promotion->getCheckOut()) {
                    $discountPercent = $promotion->getDiscountPercent();
                    break;
                }
            }

            $effectiveAmountCents = null !== $discountPercent
                ? (int) round($rateAmountCents * (1 - $discountPercent / 100))
                : $rateAmountCents;

            $nights[] = [
                'date' => $current,
                'rateAmountCents' => $rateAmountCents,
                'discountPercent' => $discountPercent,
                'effectiveAmountCents' => $effectiveAmountCents,
            ];
            $total += $effectiveAmountCents;

            $current = $current->modify('+1 day');
        }

        return new PricingQuote(
            roomId: $roomId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            totalAmountCents: $total,
            nights: array_map(
                static fn(array $n) => new NightPricingDetail($n['date'], $n['rateAmountCents'], $n['discountPercent'], $n['effectiveAmountCents']),
                $nights,
            ),
        );
    }
}
