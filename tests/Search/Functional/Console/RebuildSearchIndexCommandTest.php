<?php

declare(strict_types=1);

namespace App\Tests\Search\Functional\Console;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

#[Group('functional')]
final class RebuildSearchIndexCommandTest extends KernelTestCase
{
    private const HOTEL_ID = '11111111-1111-1111-1111-111111111111';
    private const ROOM_TYPE_ID = '22222222-2222-2222-2222-222222222222';
    private const ROOM_ID = '33333333-3333-3333-3333-333333333333';
    private const HOLD_ID = '44444444-4444-4444-4444-444444444444';
    private const RESERVATION_ID = '55555555-5555-5555-5555-555555555555';
    private const BLOCKED_PERIOD_ID = '66666666-6666-6666-6666-666666666666';
    private KernelInterface $appKernel;
    private Connection $hotelConnection;
    private Connection $roomConnection;
    private Connection $pricingConnection;
    private Connection $availabilityConnection;
    private Connection $searchConnection;

    protected function setUp(): void
    {
        $this->appKernel = self::bootKernel();
        $container = static::getContainer();

        $this->hotelConnection = $container->get('doctrine.dbal.hotel_connection');
        $this->roomConnection = $container->get('doctrine.dbal.room_connection');
        $this->pricingConnection = $container->get('doctrine.dbal.pricing_connection');
        $this->availabilityConnection = $container->get('doctrine.dbal.availability_connection');
        $this->searchConnection = $container->get('doctrine.dbal.search_connection');

        $this->cleanUp();
        $this->insertFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    #[Test]
    public function itExitsSuccessfully(): void
    {
        $application = new Application($this->appKernel);
        $tester = new CommandTester($application->find('search:rebuild-index'));

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
    }

    #[Test]
    public function itRebuildsHotelRoomTypes(): void
    {
        $application = new Application($this->appKernel);
        $tester = new CommandTester($application->find('search:rebuild-index'));
        $tester->execute([]);

        /** @var array{room_type_id: string, hotel_id: string, hotel_name: string, city: string, guest_capacity: int}|false $row */
        $row = $this->searchConnection->fetchAssociative(
            'SELECT room_type_id, hotel_id, hotel_name, city, guest_capacity FROM hotel_room_types WHERE room_type_id = :id',
            ['id' => self::ROOM_TYPE_ID],
        );

        self::assertNotFalse($row);
        self::assertSame(self::HOTEL_ID, $row['hotel_id']);
        self::assertSame('Test Hotel', $row['hotel_name']);
        self::assertSame('Paris', $row['city']);
        self::assertSame(2, $row['guest_capacity']);
    }

    #[Test]
    public function itRebuildsRoomIndex(): void
    {
        $application = new Application($this->appKernel);
        $tester = new CommandTester($application->find('search:rebuild-index'));
        $tester->execute([]);

        /** @var array{room_id: string, room_type_id: string, hotel_id: string}|false $row */
        $row = $this->searchConnection->fetchAssociative(
            'SELECT room_id, room_type_id, hotel_id FROM room_index WHERE room_id = :id',
            ['id' => self::ROOM_ID],
        );

        self::assertNotFalse($row);
        self::assertSame(self::ROOM_TYPE_ID, $row['room_type_id']);
        self::assertSame(self::HOTEL_ID, $row['hotel_id']);
    }

    #[Test]
    public function itAppliesBaseRates(): void
    {
        $application = new Application($this->appKernel);
        $tester = new CommandTester($application->find('search:rebuild-index'));
        $tester->execute([]);

        /** @var array{base_price_cents: int}|false $row */
        $row = $this->searchConnection->fetchAssociative(
            'SELECT base_price_cents FROM hotel_room_types WHERE room_type_id = :id',
            ['id' => self::ROOM_TYPE_ID],
        );

        self::assertNotFalse($row);
        self::assertSame(9900, $row['base_price_cents']);
    }

    #[Test]
    public function itRebuildsUnavailablePeriods(): void
    {
        $application = new Application($this->appKernel);
        $tester = new CommandTester($application->find('search:rebuild-index'));
        $tester->execute([]);

        $count = $this->searchConnection->fetchOne(
            'SELECT COUNT(*) FROM unavailable_periods WHERE room_id = :roomId',
            ['roomId' => self::ROOM_ID],
        );

        self::assertSame(2, $count); // 1 hold + 1 blocked period
    }

    #[Test]
    public function itClearsStaleDataBeforeRebuilding(): void
    {
        // Insert stale data in search tables with a different (non-existent) room type
        $this->searchConnection->executeStatement(
            "INSERT INTO hotel_room_types
                (room_type_id, hotel_id, hotel_name, city, country, room_type_name, guest_capacity, bed_composition, room_amenities, hotel_amenities)
             VALUES
                ('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', :hotelId, 'Stale Hotel', 'Lyon', 'FR', 'Stale Room', 1, '[]', '[]', '[]')",
            ['hotelId' => self::HOTEL_ID],
        );

        $application = new Application($this->appKernel);
        $tester = new CommandTester($application->find('search:rebuild-index'));
        $tester->execute([]);

        $count = $this->searchConnection->fetchOne('SELECT COUNT(*) FROM hotel_room_types');
        self::assertSame(1, $count); // only the real one remains
    }

    private function insertFixtures(): void
    {
        $this->hotelConnection->executeStatement(
            'INSERT INTO hotel (id, name, street_address, postal_code, city, country, search_key, created_at)
             VALUES (:id, :name, :street, :postal, :city, :country, :key, NOW())',
            [
                'id' => self::HOTEL_ID,
                'name' => 'Test Hotel',
                'street' => '1 rue de la Paix',
                'postal' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
                'key' => 'test-hotel-paris-fr',
            ],
        );

        $this->roomConnection->executeStatement(
            'INSERT INTO room_type (id, hotel_id, name, living_space_count, guest_capacity, is_accessible, bed_composition, created_at)
             VALUES (:id, :hotelId, :name, 1, 2, false, :beds, NOW())',
            [
                'id' => self::ROOM_TYPE_ID,
                'hotelId' => self::HOTEL_ID,
                'name' => 'Standard Double',
                'beds' => '[{"type":"double","count":1}]',
            ],
        );

        $this->roomConnection->executeStatement(
            "INSERT INTO room (id, hotel_id, room_type_id, room_number, room_floor, created_at)
             VALUES (:id, :hotelId, :roomTypeId, '101', 1, NOW())",
            [
                'id' => self::ROOM_ID,
                'hotelId' => self::HOTEL_ID,
                'roomTypeId' => self::ROOM_TYPE_ID,
            ],
        );

        $this->pricingConnection->executeStatement(
            'INSERT INTO base_rate (room_id, amount_cents, updated_at) VALUES (:roomId, 9900, NOW())',
            ['roomId' => self::ROOM_ID],
        );

        $this->availabilityConnection->executeStatement(
            "INSERT INTO hold (id, room_id, reservation_id, check_in, check_out, expires_at, created_at)
             VALUES (:id, :roomId, :reservationId, '2027-07-01', '2027-07-05', NOW() + INTERVAL '15 minutes', NOW())",
            ['id' => self::HOLD_ID, 'roomId' => self::ROOM_ID, 'reservationId' => self::RESERVATION_ID],
        );

        $this->availabilityConnection->executeStatement(
            "INSERT INTO blocked_period (id, room_id, check_in, check_out, created_at)
             VALUES (:id, :roomId, '2027-08-01', '2027-08-10', NOW())",
            ['id' => self::BLOCKED_PERIOD_ID, 'roomId' => self::ROOM_ID],
        );
    }

    private function cleanUp(): void
    {
        $this->searchConnection->executeStatement('DELETE FROM unavailable_periods');
        $this->searchConnection->executeStatement('DELETE FROM room_index');
        $this->searchConnection->executeStatement('DELETE FROM hotel_room_types');

        $this->availabilityConnection->executeStatement('DELETE FROM blocked_period WHERE id = :id', ['id' => self::BLOCKED_PERIOD_ID]);
        $this->availabilityConnection->executeStatement('DELETE FROM hold WHERE id = :id', ['id' => self::HOLD_ID]);
        $this->pricingConnection->executeStatement('DELETE FROM base_rate WHERE room_id = :id', ['id' => self::ROOM_ID]);
        $this->roomConnection->executeStatement('DELETE FROM room WHERE id = :id', ['id' => self::ROOM_ID]);
        $this->roomConnection->executeStatement('DELETE FROM room_type WHERE id = :id', ['id' => self::ROOM_TYPE_ID]);
        $this->hotelConnection->executeStatement('DELETE FROM hotel WHERE id = :id', ['id' => self::HOTEL_ID]);
    }
}
