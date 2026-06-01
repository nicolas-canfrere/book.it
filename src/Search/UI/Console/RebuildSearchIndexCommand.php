<?php

declare(strict_types=1);

namespace App\Search\UI\Console;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Search\Domain\Port\RoomIndexWriterInterface;
use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'search:rebuild-index', description: 'Truncate and rebuild the search read model from source data')]
final class RebuildSearchIndexCommand extends Command
{
    public function __construct(
        private readonly HotelRoomTypeWriterInterface $hotelRoomTypeWriter,
        private readonly RoomIndexWriterInterface $roomIndexWriter,
        private readonly UnavailablePeriodWriterInterface $unavailablePeriodWriter,
        private readonly Connection $searchConnection,
        private readonly Connection $roomConnection,
        private readonly Connection $pricingConnection,
        private readonly Connection $availabilityConnection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Rebuilding search index...');
        $output->writeln('<comment>Warning: search results will be empty during this operation.</comment>');

        return Command::SUCCESS;
    }
}
