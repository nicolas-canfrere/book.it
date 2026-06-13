<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBookerWithCredentials;

use App\Booker\Domain\Exception\BookerAlreadyExistsException;
use App\Booker\Domain\Exception\BookerUnderageException;
use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class RegisterBookerWithCredentialsCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BookerRepositoryInterface $bookerRepository,
        private ExternalAccountRegistrarInterface $accountRegistrar,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RegisterBookerWithCredentialsCommand $command): void
    {
        $age = $command->registeredAt->diff($command->dateOfBirth)->y;

        if ($age < 18) {
            throw new BookerUnderageException();
        }

        if ($this->bookerRepository->existsByEmail($command->email)) {
            throw new BookerAlreadyExistsException($command->email);
        }

        $bookerId = $command->id;
        $this->accountRegistrar->register($bookerId, $command->email, $command->password);

        try {
            $this->bookerRepository->add(new Booker(
                $bookerId,
                $command->firstName,
                $command->lastName,
                $command->email,
                $command->phone,
                $command->dateOfBirth,
                $command->registeredAt,
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Booker persistence failed after account creation — compensating', [
                'booker_id' => $command->id->value,
                'email' => $command->email,
                'error' => $e->getMessage(),
            ]);
            $this->accountRegistrar->unregister($bookerId);
            throw $e;
        }

        $this->logger->info('Booker registered', [
            'booker_id' => $command->id->value,
            'email' => $command->email,
        ]);
    }
}
