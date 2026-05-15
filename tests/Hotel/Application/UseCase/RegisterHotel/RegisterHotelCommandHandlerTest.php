<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Application\Service\RegisterHotelCommandFactory;
use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommandHandler;
use App\Hotel\Domain\Exception\HotelAlreadyExistsException;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Tests\Hotel\Infrastructure\Persistence\InMemory\InMemoryHotelRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class RegisterHotelCommandHandlerTest extends KernelTestCase
{
    private InMemoryHotelRepository $hotelRepository;
    private RegisterHotelCommandHandler $handler;
    private RegisterHotelCommandFactory $commandFactory;

    protected function setUp(): void
    {
        $this->hotelRepository = new InMemoryHotelRepository();
        static::getContainer()->set(HotelRepositoryInterface::class, $this->hotelRepository);
        $this->handler = static::getContainer()->get(RegisterHotelCommandHandler::class);
        $this->commandFactory = static::getContainer()->get(RegisterHotelCommandFactory::class);
    }

    #[Test]
    public function itPersistsTheHotel(): void
    {
        $command = $this->commandFactory->create(
            name: 'Hotel Ibis Paris',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        ($this->handler)($command);

        $hotel = $this->hotelRepository->get($command->id);

        self::assertNotNull($hotel);
        self::assertSame($command->id, $hotel->id);
        self::assertSame($command->name, $hotel->name);
        self::assertSame('15 rue de Rivoli', $hotel->address->streetAddress);
        self::assertSame('75001', $hotel->address->postalCode);
        self::assertSame('Paris', $hotel->address->city);
        self::assertSame('FR', $hotel->address->country);
        self::assertEquals($command->createdAt, $hotel->createdAt);
    }

    #[Test]
    public function itThrowsWhenHotelAlreadyExists(): void
    {
        $command = $this->commandFactory->create(
            name: 'Hotel Ibis Paris',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        ($this->handler)($command);

        $this->expectException(HotelAlreadyExistsException::class);

        $duplicate = $this->commandFactory->create(
            name: 'Hotel Ibis Paris',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        ($this->handler)($duplicate);
    }

    #[Test]
    public function itAllowsSameNameInDifferentCity(): void
    {
        $paris = $this->commandFactory->create(
            name: 'Hotel Ibis',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        $lyon = $this->commandFactory->create(
            name: 'Hotel Ibis',
            streetAddress: '10 rue de la Republique',
            postalCode: '69001',
            city: 'Lyon',
            country: 'FR',
        );

        ($this->handler)($paris);
        ($this->handler)($lyon);

        self::assertNotNull($this->hotelRepository->get($paris->id));
        self::assertNotNull($this->hotelRepository->get($lyon->id));
    }
}
