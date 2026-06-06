<?php

declare(strict_types=1);

namespace App\Booker\Application\Service;

use App\Booker\Application\UseCase\RegisterBookerWithCredentials\RegisterBookerWithCredentialsCommand;
use App\Booker\Domain\Port\BookerIdGeneratorInterface;
use Psr\Clock\ClockInterface;

final readonly class RegisterBookerWithCredentialsCommandFactory
{
    public function __construct(
        private BookerIdGeneratorInterface $bookerIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $dateOfBirth,
        string $password,
    ): RegisterBookerWithCredentialsCommand {
        return new RegisterBookerWithCredentialsCommand(
            $this->bookerIdGenerator->generate(),
            $firstName,
            $lastName,
            $email,
            $phone,
            new \DateTimeImmutable($dateOfBirth),
            $password,
            $this->clock->now(),
        );
    }
}
