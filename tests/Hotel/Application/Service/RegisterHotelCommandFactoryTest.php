<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\Service;

use App\Hotel\Application\Service\HotelIdGeneratorInterface;
use App\Hotel\Application\Service\RegisterHotelCommandFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

#[Group('unit')]
final class RegisterHotelCommandFactoryTest extends TestCase
{
    private RegisterHotelCommandFactory $factory;

    protected function setUp(): void
    {
        $idGenerator = $this->createStub(HotelIdGeneratorInterface::class);
        $idGenerator->method('generate')->willReturn(Uuid::v7()->toRfc4122());

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable());

        $this->factory = new RegisterHotelCommandFactory($idGenerator, $clock);
    }

    #[Test]
    public function itThrowsWhenAnyFieldIsNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create(
            name: null,
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );
    }
}
