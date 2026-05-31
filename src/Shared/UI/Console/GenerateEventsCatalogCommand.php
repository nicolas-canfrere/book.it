<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'app:events:catalog', description: 'Generate domainevents.yaml from registered domain event listeners')]
final class GenerateEventsCatalogCommand extends Command
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $catalog = [];

        foreach ($this->eventDispatcher->getListeners() as $eventClass => $listeners) {
            if (!str_starts_with($eventClass, 'App\\Shared\\Domain\\Event\\')) {
                continue;
            }

            $reflection = new \ReflectionClass($eventClass);
            $properties = [];
            $constructor = $reflection->getConstructor();

            if ($constructor !== null) {
                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();
                    $properties[$param->getName()] = $type !== null ? (string) $type : 'mixed';
                }
            }

            $listenerEntries = [];
            foreach ($listeners as $listener) {
                if (!is_array($listener) || !is_object($listener[0])) {
                    continue;
                }
                $listenerClass = $listener[0]::class;
                if (!str_starts_with($listenerClass, 'App\\')) {
                    continue;
                }
                $parts = explode('\\', $listenerClass);
                $listenerEntries[] = [
                    'context' => $parts[1] ?? 'Unknown',
                    'class' => $listenerClass,
                ];
            }

            $catalog[$reflection->getShortName()] = [
                'class' => $eventClass,
                'properties' => $properties,
                'listeners' => $listenerEntries,
            ];
        }

        $yaml = Yaml::dump([
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'events' => $catalog,
        ], 4, 2);

        file_put_contents($this->projectDir.'/domainevents.yaml', $yaml);

        $output->writeln(sprintf('<info>Generated domainevents.yaml with %d events.</info>', count($catalog)));

        return Command::SUCCESS;
    }
}
