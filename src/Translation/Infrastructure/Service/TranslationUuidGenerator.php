<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Service;

use App\Translation\Domain\Port\TranslationIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class TranslationUuidGenerator implements TranslationIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
