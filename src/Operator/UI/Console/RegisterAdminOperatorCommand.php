<?php

declare(strict_types=1);

namespace App\Operator\UI\Console;

use App\Operator\Application\Service\RegisterOperatorCommandFactory;
use App\Operator\Application\UseCase\AssignAdminRoleToOperator\AssignAdminRoleToOperatorCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'operator:register-admin',
    description: 'Register a new operator and grant them ROLE_ADMIN in Keycloak',
)]
final class RegisterAdminOperatorCommand extends Command
{
    public function __construct(
        private readonly RegisterOperatorCommandFactory $commandFactory,
        private readonly SyncCommandBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('firstName', InputArgument::REQUIRED, 'First name')
            ->addArgument('lastName', InputArgument::REQUIRED, 'Last name')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address')
            ->addArgument('phone', InputArgument::REQUIRED, 'Phone number (e.g. +33612345678)')
            ->addArgument('password', InputArgument::REQUIRED, 'Password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $firstName */
        $firstName = $input->getArgument('firstName');
        /** @var string $lastName */
        $lastName = $input->getArgument('lastName');
        /** @var string $email */
        $email = $input->getArgument('email');
        /** @var string $phone */
        $phone = $input->getArgument('phone');
        /** @var string $password */
        $password = $input->getArgument('password');

        $registerCommand = $this->commandFactory->create(
            $firstName,
            $lastName,
            $email,
            $phone,
            $password,
        );

        $this->commandBus->execute($registerCommand);
        $this->commandBus->execute(new AssignAdminRoleToOperatorCommand($registerCommand->id));

        $output->writeln(sprintf(
            '<info>Admin operator "%s" registered with id %s</info>',
            $registerCommand->email,
            $registerCommand->id,
        ));

        return Command::SUCCESS;
    }
}
