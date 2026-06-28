<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Contract;

use App\Operator\Application\Contract\OwnerOperatorRegistrarInterface;
use App\Operator\Domain\Exception\OperatorAlreadyExistsException;
use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use App\Operator\Domain\ValueObject\OperatorRole;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Shared\Domain\ValueObject\OperatorId;
use App\Shared\Domain\ValueObject\OrganizationId;
use Psr\Log\LoggerInterface;

final readonly class DoctrineOwnerOperatorRegistrar implements OwnerOperatorRegistrarInterface
{
    public function __construct(
        private OperatorRepositoryInterface $operatorRepository,
        private AccountRegistrarInterface $accountRegistrar,
        private LoggerInterface $logger,
    ) {
    }

    public function registerOwner(
        string $operatorId,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
        string $organizationId,
        \DateTimeImmutable $registeredAt,
    ): void {
        if ($this->operatorRepository->existsByEmail($email)) {
            throw new OperatorAlreadyExistsException($email);
        }

        $this->accountRegistrar->register($operatorId, 'operator', $email, $password);
        $this->accountRegistrar->setOrganizationId($operatorId, 'operator', $organizationId);

        try {
            $this->operatorRepository->add(new Operator(
                new OperatorId($operatorId),
                $firstName,
                $lastName,
                $email,
                $phone,
                $registeredAt,
                new OrganizationId($organizationId),
                OperatorRole::Owner,
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Owner operator DB save failed — compensating Keycloak', [
                'operator_id' => $operatorId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            $this->accountRegistrar->unregister($operatorId, 'operator');
            throw $e;
        }
    }
}
