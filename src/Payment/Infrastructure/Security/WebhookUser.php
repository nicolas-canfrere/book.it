<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class WebhookUser implements UserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_WEBHOOK'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'payment-provider';
    }
}
