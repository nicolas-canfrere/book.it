<?php

declare(strict_types=1);

namespace App\Booker\Application\Service;

use App\Booker\Application\UseCase\RegisterBooker\RegisterBookerCommand;
use Psr\Clock\ClockInterface;

final readonly class RegisterBookerCommandFactory
{
    public function __construct(
        private BookerIdGeneratorInterface $bookerIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        ?string $firstName,
        ?string $lastName,
        ?string $email,
        ?string $phone,
        ?string $dateOfBirth,
    ): RegisterBookerCommand {
        if (null === $firstName || null === $lastName || null === $email || null === $phone || null === $dateOfBirth) {
            throw new \InvalidArgumentException('All booker fields are required.');
        }

        return new RegisterBookerCommand(
            $this->bookerIdGenerator->generate(),
            $firstName,
            $lastName,
            $email,
            $phone,
            new \DateTimeImmutable($dateOfBirth),
            $this->clock->now(),
        );
    }
}
