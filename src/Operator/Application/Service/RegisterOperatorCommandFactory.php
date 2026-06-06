<?php

declare(strict_types=1);

namespace App\Operator\Application\Service;

use App\Operator\Application\UseCase\RegisterOperator\RegisterOperatorCommand;
use App\Operator\Domain\Port\OperatorIdGeneratorInterface;
use Psr\Clock\ClockInterface;

final readonly class RegisterOperatorCommandFactory
{
    public function __construct(
        private OperatorIdGeneratorInterface $operatorIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
    ): RegisterOperatorCommand {
        return new RegisterOperatorCommand(
            $this->operatorIdGenerator->generate(),
            $firstName,
            $lastName,
            $email,
            $phone,
            $password,
            $this->clock->now(),
        );
    }
}
