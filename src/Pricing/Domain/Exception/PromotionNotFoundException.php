<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class PromotionNotFoundException extends \RuntimeException
{
    public function __construct(string $promotionId)
    {
        parent::__construct(sprintf('Promotion "%s" not found.', $promotionId));
    }
}
