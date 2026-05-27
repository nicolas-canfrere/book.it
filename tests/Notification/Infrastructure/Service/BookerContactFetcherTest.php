<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Booker\Domain\Model\Booker;
use App\Notification\Infrastructure\Service\BookerContactFetcher;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerContactFetcherTest extends TestCase
{
    public function test_returns_contact_when_booker_found(): void
    {
        $booker = new Booker(
            id: 'booker-001',
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean.dupont@example.com',
            phone: '+33600000000',
            dateOfBirth: new \DateTimeImmutable('1980-01-01'),
            registeredAt: new \DateTimeImmutable(),
        );

        $queryBus = new class($booker) implements SyncQueryBusInterface {
            public function __construct(private readonly Booker $booker)
            {
            }

            public function ask(object $query): mixed
            {
                return $this->booker;
            }
        };

        $fetcher = new BookerContactFetcher($queryBus);
        $contact = $fetcher->fetch('booker-001');

        self::assertNotNull($contact);
        self::assertSame('Jean', $contact->firstName);
        self::assertSame('Dupont', $contact->lastName);
        self::assertSame('jean.dupont@example.com', $contact->email);
    }

    public function test_returns_null_when_booker_not_found(): void
    {
        $queryBus = new class implements SyncQueryBusInterface {
            public function ask(object $query): mixed
            {
                return null;
            }
        };

        $fetcher = new BookerContactFetcher($queryBus);

        self::assertNull($fetcher->fetch('unknown'));
    }
}
