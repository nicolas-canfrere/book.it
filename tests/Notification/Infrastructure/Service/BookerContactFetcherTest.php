<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Booker\Application\Contract\BookerFinderInterface;
use App\Booker\Application\Contract\BookerView;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Infrastructure\Service\BookerContactFetcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerContactFetcherTest extends TestCase
{
    private BookerFinderInterface&\PHPUnit\Framework\MockObject\Stub $bookerFinder;
    private BookerContactFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->bookerFinder = $this->createStub(BookerFinderInterface::class);
        $this->fetcher = new BookerContactFetcher($this->bookerFinder);
    }

    #[Test]
    public function itReturnsContactWhenBookerFound(): void
    {
        $this->bookerFinder->method('find')->willReturn(
            new BookerView('booker-1', 'Alice', 'Dupont', 'alice@example.com')
        );

        $contact = $this->fetcher->fetch('booker-1');

        self::assertNotNull($contact);
        self::assertSame('Alice', $contact->firstName);
        self::assertSame('Dupont', $contact->lastName);
        self::assertSame('alice@example.com', $contact->email);
    }

    #[Test]
    public function itReturnsNullWhenBookerNotFound(): void
    {
        $this->bookerFinder->method('find')->willReturn(null);

        self::assertNull($this->fetcher->fetch('unknown'));
    }
}
