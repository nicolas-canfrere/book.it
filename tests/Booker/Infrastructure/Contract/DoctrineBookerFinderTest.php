<?php

declare(strict_types=1);

namespace Tests\Booker\Infrastructure\Contract;

use App\Booker\Application\Contract\BookerFinderInterface;
use App\Booker\Application\Contract\BookerView;
use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Booker\Infrastructure\Contract\DoctrineBookerFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineBookerFinderTest extends TestCase
{
    private BookerRepositoryInterface&Stub $repository;
    private BookerFinderInterface $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(BookerRepositoryInterface::class);
        $this->finder = new DoctrineBookerFinder($this->repository);
    }

    #[Test]
    public function itReturnsNullWhenBookerDoesNotExist(): void
    {
        $this->repository->method('get')->willReturn(null);

        self::assertNull($this->finder->find('unknown-id'));
    }

    #[Test]
    public function itReturnsBookerViewWhenBookerExists(): void
    {
        $booker = new Booker(
            id: 'b1b2b3b4-0000-0000-0000-000000000001',
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean.dupont@example.com',
            phone: '+33600000000',
            dateOfBirth: new \DateTimeImmutable('1985-03-15'),
            registeredAt: new \DateTimeImmutable('2024-01-01'),
        );
        $this->repository->method('get')->willReturn($booker);

        $view = $this->finder->find('b1b2b3b4-0000-0000-0000-000000000001');

        self::assertInstanceOf(BookerView::class, $view);
        self::assertSame('b1b2b3b4-0000-0000-0000-000000000001', $view->id);
        self::assertSame('Jean', $view->firstName);
        self::assertSame('Dupont', $view->lastName);
        self::assertSame('jean.dupont@example.com', $view->email);
    }
}
