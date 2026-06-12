<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

interface PromotionIdGeneratorInterface
{
    public function generate(): string;
}
