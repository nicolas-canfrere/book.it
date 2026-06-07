<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:fixtures:load', description: 'Load fixtures (truncates all tables first)')]
final class LoadFixturesCommand extends Command
{
    public function __construct(private readonly Connection $bookitConnection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->truncateAll($io);
        $this->loadHotel($io);
        $this->loadRoomTypes($io);
        $this->loadRooms($io);
        $this->loadPricingBaseRates($io);
        $this->loadPricingRatePeriods($io);

        $io->success('Fixtures loaded.');

        return Command::SUCCESS;
    }

    private function truncateAll(SymfonyStyle $io): void
    {
        $io->section('Truncating tables');

        $this->bookitConnection->executeStatement(
            'TRUNCATE hotel, room_type, room, booker, reservation,
             blocked_period, availability_hold,
             pricing_base_rate, pricing_rate_period, pricing_promotion, pricing_cancellation_policy
             CASCADE',
        );

        $io->text('Done.');
    }

    private function loadHotel(SymfonyStyle $io): void
    {
        $io->section('Loading hotel');

        $this->bookitConnection->insert('hotel', [
            'id' => FixtureIds::HOTEL_ID,
            'name' => 'Grand Hôtel du Lac',
            'street_address' => '15 rue de la Paix',
            'postal_code' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
            'search_key' => 'grand-hotel-du-lac|15-rue-de-la-paix|75001|paris|fr',
            'stars' => 4,
            'superior' => false,
            'created_at' => '2026-01-01 00:00:00',
        ], ['superior' => Types::BOOLEAN]);

        $io->text('Grand Hôtel du Lac — Paris (4*)');
    }

    private function loadRoomTypes(SymfonyStyle $io): void
    {
        $io->section('Loading room types');

        $this->bookitConnection->insert('room_type', [
            'id' => FixtureIds::ROOM_TYPE_STANDARD_ID,
            'hotel_id' => FixtureIds::HOTEL_ID,
            'name' => 'Chambre Standard',
            'living_space_count' => 1,
            'surface_m2' => 25,
            'guest_capacity' => 2,
            'is_accessible' => false,
            'bed_composition' => json_encode([['type' => 'double', 'count' => 1]], \JSON_THROW_ON_ERROR),
            'created_at' => '2026-01-01 00:00:00',
        ], ['is_accessible' => Types::BOOLEAN]);

        $this->bookitConnection->insert('room_type', [
            'id' => FixtureIds::ROOM_TYPE_SUITE_ID,
            'hotel_id' => FixtureIds::HOTEL_ID,
            'name' => 'Suite Deluxe',
            'living_space_count' => 2,
            'surface_m2' => 55,
            'guest_capacity' => 4,
            'is_accessible' => true,
            'bed_composition' => json_encode([['type' => 'king', 'count' => 1]], \JSON_THROW_ON_ERROR),
            'created_at' => '2026-01-01 00:00:00',
        ], ['is_accessible' => Types::BOOLEAN]);

        $io->text('Chambre Standard, Suite Deluxe');
    }

    private function loadPricingRatePeriods(SymfonyStyle $io): void
    {
        $io->section('Loading pricing rate periods');

        $periods = [
            [FixtureIds::RATE_PERIOD_SUMMER_101_ID, FixtureIds::ROOM_101_ID, 11250],
            [FixtureIds::RATE_PERIOD_SUMMER_102_ID, FixtureIds::ROOM_102_ID, 11250],
            [FixtureIds::RATE_PERIOD_SUMMER_201_ID, FixtureIds::ROOM_201_ID, 31250],
            [FixtureIds::RATE_PERIOD_SUMMER_202_ID, FixtureIds::ROOM_202_ID, 31250],
        ];

        foreach ($periods as [$id, $roomId, $amountCents]) {
            $this->bookitConnection->insert('pricing_rate_period', [
                'id' => $id,
                'room_id' => $roomId,
                'check_in' => '2026-07-01',
                'check_out' => '2026-08-31',
                'amount_cents' => $amountCents,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ]);
        }

        $io->text('Summer 2026 (+25%): standard 112.50€, suite 312.50€');
    }

    private function loadPricingBaseRates(SymfonyStyle $io): void
    {
        $io->section('Loading pricing base rates');

        $rates = [
            [FixtureIds::ROOM_101_ID, 9000],
            [FixtureIds::ROOM_102_ID, 9000],
            [FixtureIds::ROOM_201_ID, 25000],
            [FixtureIds::ROOM_202_ID, 25000],
        ];

        foreach ($rates as [$roomId, $amountCents]) {
            $this->bookitConnection->insert('pricing_base_rate', [
                'room_id' => $roomId,
                'amount_cents' => $amountCents,
                'updated_at' => '2026-01-01 00:00:00',
            ]);
            $io->text(sprintf('Room %s — %d€/night', $roomId, $amountCents / 100));
        }
    }

    private function loadRooms(SymfonyStyle $io): void
    {
        $io->section('Loading rooms');

        $rooms = [
            [FixtureIds::ROOM_101_ID, '101', 1, FixtureIds::ROOM_TYPE_STANDARD_ID],
            [FixtureIds::ROOM_102_ID, '102', 1, FixtureIds::ROOM_TYPE_STANDARD_ID],
            [FixtureIds::ROOM_201_ID, '201', 2, FixtureIds::ROOM_TYPE_SUITE_ID],
            [FixtureIds::ROOM_202_ID, '202', 2, FixtureIds::ROOM_TYPE_SUITE_ID],
        ];

        foreach ($rooms as [$id, $number, $floor, $roomTypeId]) {
            $this->bookitConnection->insert('room', [
                'id' => $id,
                'hotel_id' => FixtureIds::HOTEL_ID,
                'room_number' => $number,
                'room_floor' => $floor,
                'room_type_id' => $roomTypeId,
                'created_at' => '2026-01-01 00:00:00',
            ]);
            $io->text("Room {$number} (floor {$floor})");
        }
    }
}
