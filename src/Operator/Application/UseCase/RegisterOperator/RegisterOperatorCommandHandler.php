<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\RegisterOperator;

use App\Operator\Domain\Exception\OperatorAlreadyExistsException;
use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class RegisterOperatorCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private OperatorRepositoryInterface $operatorRepository,
        private ExternalAccountRegistrarInterface $accountRegistrar,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RegisterOperatorCommand $command): void
    {
        if ($this->operatorRepository->existsByEmail($command->email)) {
            throw new OperatorAlreadyExistsException($command->email);
        }

        $this->accountRegistrar->register($command->id, $command->email, $command->password);

        try {
            $this->operatorRepository->add(new Operator(
                $command->id,
                $command->firstName,
                $command->lastName,
                $command->email,
                $command->phone,
                $command->registeredAt,
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Operator persistence failed after account creation — compensating', [
                'operator_id' => $command->id->value,
                'email' => $command->email,
                'error' => $e->getMessage(),
            ]);
            $this->accountRegistrar->unregister($command->id);
            throw $e;
        }

        $this->logger->info('Operator registered', [
            'operator_id' => $command->id->value,
            'email' => $command->email,
        ]);
    }
}
