<?php

declare(strict_types=1);

namespace App\Search\UI\Console;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Search\Domain\Port\RoomIndexWriterInterface;
use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use App\Shared\Domain\ValueObject\HotelId;
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

        $output->write('[1/6] Truncating search tables... ');
        $this->searchConnection->executeStatement('TRUNCATE unavailable_periods, room_index, hotel_room_types CASCADE');
        $output->writeln('done');

        $output->write('[2/6] Rebuilding hotel_room_types... ');
        /** @var list<array{id: string, hotel_id: string, name: string, guest_capacity: int|string, bed_composition: string}> $roomTypes */
        $roomTypes = $this->roomConnection->fetchAllAssociative(
            'SELECT id, hotel_id, name, guest_capacity, bed_composition FROM room_type',
        );
        foreach ($roomTypes as $rt) {
            /** @var list<array{type: string, count: int}> $beds */
            $beds = json_decode($rt['bed_composition'], true, 512, \JSON_THROW_ON_ERROR);
            $this->hotelRoomTypeWriter->upsertRoomType(
                roomTypeId: $rt['id'],
                hotelId: new HotelId($rt['hotel_id']),
                name: $rt['name'],
                guestCapacity: (int) $rt['guest_capacity'],
                bedComposition: $beds,
            );
        }
        $output->writeln(sprintf('%d room types inserted', count($roomTypes)));

        $output->write('[3/6] Rebuilding room_index... ');
        /** @var list<array{id: string, room_type_id: string, hotel_id: string}> $rooms */
        $rooms = $this->roomConnection->fetchAllAssociative(
            'SELECT id, room_type_id, hotel_id FROM room',
        );
        foreach ($rooms as $room) {
            $this->roomIndexWriter->upsert(
                roomId: $room['id'],
                roomTypeId: $room['room_type_id'],
                hotelId: new HotelId($room['hotel_id']),
            );
        }
        $output->writeln(sprintf('%d rooms inserted', count($rooms)));

        $output->write('[4/6] Applying base rates... ');
        /** @var list<array{room_id: string, amount_cents: int|string}> $baseRates */
        $baseRates = $this->pricingConnection->fetchAllAssociative(
            'SELECT room_id, amount_cents FROM base_rate',
        );
        foreach ($baseRates as $rate) {
            $this->hotelRoomTypeWriter->updateBaseRateByRoom(
                roomId: $rate['room_id'],
                amountCents: (int) $rate['amount_cents'],
            );
        }
        $output->writeln(sprintf('%d rates applied', count($baseRates)));

        $output->write('[5/6] Rebuilding holds... ');
        /** @var list<array{id: string, room_id: string, check_in: string, check_out: string}> $holds */
        $holds = $this->availabilityConnection->fetchAllAssociative(
            'SELECT id, room_id, check_in, check_out FROM hold WHERE expires_at > NOW()',
        );
        foreach ($holds as $hold) {
            $this->unavailablePeriodWriter->add(
                sourceId: $hold['id'],
                roomId: $hold['room_id'],
                checkIn: new \DateTimeImmutable($hold['check_in']),
                checkOut: new \DateTimeImmutable($hold['check_out']),
            );
        }
        $output->writeln(sprintf('%d holds inserted', count($holds)));

        $output->write('[6/6] Rebuilding blocked periods... ');
        /** @var list<array{id: string, room_id: string, check_in: string, check_out: string}> $blockedPeriods */
        $blockedPeriods = $this->availabilityConnection->fetchAllAssociative(
            'SELECT id, room_id, check_in, check_out FROM blocked_period',
        );
        foreach ($blockedPeriods as $bp) {
            $this->unavailablePeriodWriter->add(
                sourceId: $bp['id'],
                roomId: $bp['room_id'],
                checkIn: new \DateTimeImmutable($bp['check_in']),
                checkOut: new \DateTimeImmutable($bp['check_out']),
            );
        }
        $output->writeln(sprintf('%d periods inserted', count($blockedPeriods)));

        $output->writeln('Done.');

        return Command::SUCCESS;
    }
}
