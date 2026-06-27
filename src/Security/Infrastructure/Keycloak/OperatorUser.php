<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Component\Security\Core\User\UserInterface;

final class OperatorUser implements UserInterface
{
    /**
     * @var list<string>
     */
    private array $roles = [];

    /**
     * @param list<string> $roles
     */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        array $roles = [],
        public readonly ?string $organizationId = null,
    ) {
        $this->setRoles($roles);
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
        return $this->roles;
    }

    /**
     * @param list<string> $roles
     */
    private function setRoles(array $roles): void
    {
        $roles = array_merge($roles, ['ROLE_OPERATOR']);
        $this->roles = array_values(array_unique($roles));
    }
}
