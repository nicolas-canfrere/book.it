<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use App\Shared\Infrastructure\ContextMap\ContextMapChecker;
use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:contextmap:check', description: 'Validate contextmap.yaml against source')]
final class CheckContextMapCommand extends Command
{
    public function __construct(
        private readonly ContextMapChecker $checker,
        private readonly ContextMapConfigLoader $configLoader,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->configLoader->load($this->projectDir);

        if (!file_exists($config->getOutputYaml())) {
            $output->writeln('<error>contextmap.yaml not found. Run: app:contextmap:generate</error>');

            return Command::FAILURE;
        }

        $result = $this->checker->check($config->getOutputYaml(), $config->getSrcDir());

        foreach ($result['ok'] as $msg) {
            $output->writeln(sprintf('<info>[OK]   %s</info>', $msg));
        }
        foreach ($result['fail'] as $msg) {
            $output->writeln(sprintf('<error>[FAIL] %s</error>', $msg));
        }

        if ([] !== $result['fail']) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
