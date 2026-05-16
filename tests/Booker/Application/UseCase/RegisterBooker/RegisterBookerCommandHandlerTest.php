<?php

declare(strict_types=1);

namespace App\Tests\Booker\Application\UseCase\RegisterBooker;

use App\Booker\Application\Service\RegisterBookerCommandFactory;
use App\Booker\Application\UseCase\RegisterBooker\RegisterBookerCommandHandler;
use App\Booker\Domain\Exception\BookerAlreadyExistsException;
use App\Booker\Domain\Exception\BookerUnderageException;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Tests\Booker\Infrastructure\Persistence\InMemory\InMemoryBookerRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class RegisterBookerCommandHandlerTest extends KernelTestCase
{
    private InMemoryBookerRepository $bookerRepository;
    private RegisterBookerCommandHandler $handler;
    private RegisterBookerCommandFactory $commandFactory;

    protected function setUp(): void
    {
        $this->bookerRepository = new InMemoryBookerRepository();
        static::getContainer()->set(BookerRepositoryInterface::class, $this->bookerRepository);
        $this->handler = static::getContainer()->get(RegisterBookerCommandHandler::class);
        $this->commandFactory = static::getContainer()->get(RegisterBookerCommandFactory::class);
    }

    #[Test]
    public function itPersistsTheBooker(): void
    {
        $command = $this->commandFactory->create(
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean.dupont@example.com',
            phone: '+33612345678',
            dateOfBirth: '1990-05-15',
        );

        ($this->handler)($command);

        $booker = $this->bookerRepository->get($command->id);

        self::assertNotNull($booker);
        self::assertSame($command->id, $booker->id);
        self::assertSame('Jean', $booker->firstName);
        self::assertSame('Dupont', $booker->lastName);
        self::assertSame('jean.dupont@example.com', $booker->email);
        self::assertSame('+33612345678', $booker->phone);
        self::assertSame('1990-05-15', $booker->dateOfBirth->format('Y-m-d'));
        self::assertEquals($command->registeredAt, $booker->registeredAt);
    }

    #[Test]
    public function itThrowsWhenEmailAlreadyExists(): void
    {
        $command = $this->commandFactory->create(
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean@example.com',
            phone: '+33612345678',
            dateOfBirth: '1990-05-15',
        );
        ($this->handler)($command);

        $this->expectException(BookerAlreadyExistsException::class);

        $duplicate = $this->commandFactory->create(
            firstName: 'Marie',
            lastName: 'Martin',
            email: 'jean@example.com',
            phone: '+33698765432',
            dateOfBirth: '1985-03-20',
        );
        ($this->handler)($duplicate);
    }

    #[Test]
    public function itThrowsWhenBookerIsUnderage(): void
    {
        $this->expectException(BookerUnderageException::class);

        $underageDate = (new \DateTimeImmutable())->modify('-17 years +1 day')->format('Y-m-d');

        $command = $this->commandFactory->create(
            firstName: 'Young',
            lastName: 'Person',
            email: 'young@example.com',
            phone: '+33612345678',
            dateOfBirth: $underageDate,
        );
        ($this->handler)($command);
    }

    #[Test]
    public function itAcceptsBookerWhoTurns18Today(): void
    {
        $exactlyEighteenDate = (new \DateTimeImmutable())->modify('-18 years')->format('Y-m-d');

        $command = $this->commandFactory->create(
            firstName: 'Just',
            lastName: 'Adult',
            email: 'adult@example.com',
            phone: '+33612345678',
            dateOfBirth: $exactlyEighteenDate,
        );

        ($this->handler)($command);

        self::assertNotNull($this->bookerRepository->get($command->id));
    }
}
