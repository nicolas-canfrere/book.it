<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use App\Shared\Infrastructure\ContextMap\ContextMapBuilder;
use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use App\Shared\Infrastructure\ContextMap\ContractScanner;
use App\Shared\Infrastructure\ContextMap\DeptracRulesetParser;
use App\Shared\Infrastructure\ContextMap\MermaidWriter;
use App\Shared\Infrastructure\ContextMap\YamlWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:contextmap:generate', description: 'Generate contextmap.yaml and docs/context-map.md from source')]
final class GenerateContextMapCommand extends Command
{
    public function __construct(
        private readonly ContractScanner $contractScanner,
        private readonly DeptracRulesetParser $deptracRulesetParser,
        private readonly ContextMapBuilder $contextMapBuilder,
        private readonly YamlWriter $yamlWriter,
        private readonly MermaidWriter $mermaidWriter,
        private readonly ContextMapConfigLoader $configLoader,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output-yaml', null, InputOption::VALUE_REQUIRED, 'Output path for contextmap.yaml (relative to project dir)')
            ->addOption('output-mermaid', null, InputOption::VALUE_REQUIRED, 'Output path for context-map.md (relative to project dir)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->configLoader->load($this->projectDir);

        if (!file_exists($config->getDeptracFile())) {
            $output->writeln('<error>deptrac-contexts.yaml not found</error>');

            return Command::FAILURE;
        }

        if (!is_dir($config->getSrcDir())) {
            $output->writeln(sprintf('<error>src directory not found: %s</error>', $config->getSrcDir()));

            return Command::FAILURE;
        }

        $outputYamlOption = $input->getOption('output-yaml');
        $outputYaml = is_string($outputYamlOption)
            ? $this->projectDir . '/' . $outputYamlOption
            : $config->getOutputYaml();

        $outputMermaidOption = $input->getOption('output-mermaid');
        $outputMermaid = is_string($outputMermaidOption)
            ? $this->projectDir . '/' . $outputMermaidOption
            : $config->getOutputMermaid();

        $contracts = $this->contractScanner->scan($config->getSrcDir());
        $consumes = $this->deptracRulesetParser->parse($config->getDeptracFile(), $config->getExcludedLayers());
        $map = $this->contextMapBuilder->build($contracts, $consumes);

        $this->yamlWriter->write($map, $outputYaml);
        $this->mermaidWriter->write($map, $outputMermaid);

        $output->writeln(sprintf('<info>Generated %s and %s</info>', basename($outputYaml), basename($outputMermaid)));

        return Command::SUCCESS;
    }
}
