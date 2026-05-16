<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBooker;

use App\Booker\Domain\Exception\BookerAlreadyExistsException;
use App\Booker\Domain\Exception\BookerUnderageException;
use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterBookerCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BookerRepositoryInterface $bookerRepository,
    ) {
    }

    public function __invoke(RegisterBookerCommand $command): void
    {
        $age = $command->registeredAt->diff($command->dateOfBirth)->y;

        if ($age < 18) {
            throw new BookerUnderageException();
        }

        if ($this->bookerRepository->existsByEmail($command->email)) {
            throw new BookerAlreadyExistsException($command->email);
        }

        $booker = new Booker(
            $command->id,
            $command->firstName,
            $command->lastName,
            $command->email,
            $command->phone,
            $command->dateOfBirth,
            $command->registeredAt,
        );

        $this->bookerRepository->add($booker);
    }
}
