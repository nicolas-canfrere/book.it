<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\ExternalAccount;

use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;

final class NullExternalAccountRegistrar implements ExternalAccountRegistrarInterface
{
    public function register(string $bookerId, string $email, string $password): void
    {
    }

    public function unregister(string $bookerId): void
    {
    }
}
