<?php

declare(strict_types=1);

namespace App\Translation\Domain\Port;

interface TranslationIdGeneratorInterface
{
    public function generate(): string;
}
