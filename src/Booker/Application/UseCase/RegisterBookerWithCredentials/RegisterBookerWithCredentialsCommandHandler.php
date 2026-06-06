<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBookerWithCredentials;

use App\Booker\Domain\Exception\BookerAlreadyExistsException;
use App\Booker\Domain\Exception\BookerUnderageException;
use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterBookerWithCredentialsCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BookerRepositoryInterface $bookerRepository,
        private ExternalAccountRegistrarInterface $accountRegistrar,
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

        $this->accountRegistrar->register($command->id, $command->email, $command->password);

        try {
            $this->bookerRepository->add(new Booker(
                $command->id,
                $command->firstName,
                $command->lastName,
                $command->email,
                $command->phone,
                $command->dateOfBirth,
                $command->registeredAt,
            ));
        } catch (\Throwable $e) {
            $this->accountRegistrar->unregister($command->id);
            throw $e;
        }
    }
}
