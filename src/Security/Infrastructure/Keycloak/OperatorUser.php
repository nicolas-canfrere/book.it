<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class OperatorUser implements UserInterface
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('OperatorUser email cannot be empty');
        }

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_OPERATOR'];
    }

    public function eraseCredentials(): void
    {
    }
}
