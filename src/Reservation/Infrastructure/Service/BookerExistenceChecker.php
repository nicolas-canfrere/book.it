<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Shared\Domain\Port\BookerProviderInterface;

final readonly class BookerExistenceChecker implements BookerExistsInterface
{
    public function __construct(private BookerProviderInterface $bookerProvider)
    {
    }

    public function exists(string $bookerId): bool
    {
        return $this->bookerProvider->exists($bookerId);
    }
}
