<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPricingQuote;

use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetPricingQuoteQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private RoomExistsInterface $roomExists,
        private BaseRateRepositoryInterface $baseRateRepository,
        private RatePeriodRepositoryInterface $ratePeriodRepository,
        private PromotionRepositoryInterface $promotionRepository,
    ) {
    }

    /**
     * @return array{
     *     roomId: string,
     *     checkIn: string,
     *     checkOut: string,
     *     totalAmountCents: int,
     *     nights: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>
     * }
     */
    public function __invoke(GetPricingQuoteQuery $query): array
    {
        if (!$this->roomExists->exists($query->roomId)) {
            throw new RoomNotFoundException($query->roomId);
        }

        $baseRate = $this->baseRateRepository->findByRoomId($query->roomId);
        if (null === $baseRate) {
            throw new RoomHasNoBaseRateException($query->roomId);
        }

        $stayPeriod = new DatePeriod($query->checkIn, $query->checkOut);
        $ratePeriods = $this->ratePeriodRepository->findOverlappingByRoomId($query->roomId, $stayPeriod);
        $promotions = $this->promotionRepository->findOverlappingByRoomId($query->roomId, $stayPeriod);

        $nights = [];
        $total = 0;
        $current = $query->checkIn;
        while ($current < $query->checkOut) {
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
                'date' => $current->format('Y-m-d'),
                'rateAmountCents' => $rateAmountCents,
                'discountPercent' => $discountPercent,
                'effectiveAmountCents' => $effectiveAmountCents,
            ];
            $total += $effectiveAmountCents;

            $current = $current->modify('+1 day');
        }

        return [
            'roomId' => $query->roomId,
            'checkIn' => $query->checkIn->format('Y-m-d'),
            'checkOut' => $query->checkOut->format('Y-m-d'),
            'totalAmountCents' => $total,
            'nights' => $nights,
        ];
    }
}
