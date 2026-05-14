<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Application\Service\RegisterHotelCommandFactory;
use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommandHandler;
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
        $command = $this->commandFactory->create('Hotel Ibis Paris');

        ($this->handler)($command);

        $hotel = $this->hotelRepository->get($command->id);

        self::assertNotNull($hotel);
        self::assertSame($command->id, $hotel->id);
        self::assertSame($command->name, $hotel->name);
        self::assertEquals($command->createdAt, $hotel->createdAt);
    }
}
