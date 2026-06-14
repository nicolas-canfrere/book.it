<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

use App\Shared\Domain\ValueObject\BookerId;

interface ExternalAccountRegistrarInterface
{
    public function register(BookerId $bookerId, string $email, string $password): void;

    public function unregister(BookerId $bookerId): void;
}
