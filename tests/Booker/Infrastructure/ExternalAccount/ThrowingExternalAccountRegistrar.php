<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\ExternalAccount;

use App\Booker\Domain\Exception\ExternalAccountCreationException;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
use App\Shared\Domain\ValueObject\BookerId;

final class ThrowingExternalAccountRegistrar implements ExternalAccountRegistrarInterface
{
    public function register(BookerId $bookerId, string $email, string $password): void
    {
        throw new ExternalAccountCreationException($email, new \RuntimeException('Keycloak unavailable'));
    }

    public function unregister(BookerId $bookerId): void
    {
    }
}
