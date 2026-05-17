<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class PromotionOverlapException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The promotion period overlaps an existing promotion for this room.');
    }
}
