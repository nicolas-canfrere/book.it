<?php

declare(strict_types=1);

namespace Tests\Reservation\Infrastructure\Service;

use App\Booker\Application\Contract\BookerFinderInterface;
use App\Booker\Application\Contract\BookerView;
use App\Reservation\Infrastructure\Service\BookerExistenceChecker;
use App\Shared\Domain\ValueObject\BookerId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerExistenceCheckerTest extends TestCase
{
    private BookerFinderInterface&Stub $bookerFinder;
    private BookerExistenceChecker $checker;

    protected function setUp(): void
    {
        $this->bookerFinder = $this->createStub(BookerFinderInterface::class);
        $this->checker = new BookerExistenceChecker($this->bookerFinder);
    }

    #[Test]
    public function itReturnsFalseWhenBookerDoesNotExist(): void
    {
        $this->bookerFinder->method('find')->willReturn(null);

        self::assertFalse($this->checker->exists(new BookerId('unknown-id')));
    }

    #[Test]
    public function itReturnsTrueWhenBookerExists(): void
    {
        $view = new BookerView(
            id: 'b1b2b3b4-0000-0000-0000-000000000001',
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean.dupont@example.com',
        );
        $this->bookerFinder->method('find')->willReturn($view);

        self::assertTrue($this->checker->exists(new BookerId('b1b2b3b4-0000-0000-0000-000000000001')));
    }
}
