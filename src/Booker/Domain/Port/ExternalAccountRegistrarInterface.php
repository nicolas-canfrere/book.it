<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

interface ExternalAccountRegistrarInterface
{
    public function register(string $bookerId, string $email, string $password): void;

    public function unregister(string $bookerId): void;
}
