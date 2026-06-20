<?php

declare(strict_types=1);

namespace App\Geo\UI\Console;

use App\Geo\Domain\Port\GeoPlaceWriterInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'geo:import-places', description: 'Import Geo Places from a GeoNames cities dump file (e.g. cities500.txt)')]
final class ImportGeoPlacesCommand extends Command
{
    public function __construct(private readonly GeoPlaceWriterInterface $writer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the GeoNames tab-separated dump file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument('file');

        if (!is_file($path)) {
            $output->writeln("<error>File not found: {$path}</error>");

            return Command::FAILURE;
        }

        $handle = fopen($path, 'r');
        if (false === $handle) {
            $output->writeln("<error>Unable to open file: {$path}</error>");

            return Command::FAILURE;
        }

        $count = 0;
        while (false !== ($line = fgets($handle))) {
            $line = rtrim($line, "\n");
            if ('' === $line) {
                continue;
            }

            $columns = explode("\t", $line);

            $this->writer->upsert(
                id: new GeoPlaceId($columns[0]),
                name: $columns[1],
                asciiName: $columns[2],
                countryCode: $columns[8],
                admin1Code: '' !== $columns[10] ? $columns[10] : null,
            );
            ++$count;
        }

        fclose($handle);

        $output->writeln("Imported {$count} Geo Places.");

        return Command::SUCCESS;
    }
}
