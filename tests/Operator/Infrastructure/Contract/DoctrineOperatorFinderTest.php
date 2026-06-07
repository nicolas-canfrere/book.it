<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\Contract;

use App\Operator\Application\Contract\OperatorView;
use App\Operator\Infrastructure\Contract\DoctrineOperatorFinder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineOperatorFinderTest extends TestCase
{
    private Connection&MockObject $connection;
    private DoctrineOperatorFinder $finder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->finder = new DoctrineOperatorFinder($this->connection);
    }

    #[Test]
    public function itReturnsOperatorViewWhenFound(): void
    {
        $this->connection->method('fetchAssociative')
            ->with('SELECT id, email FROM operator WHERE id = ?', ['op-uuid'])
            ->willReturn(['id' => 'op-uuid', 'email' => 'op@example.com']);

        $view = $this->finder->find('op-uuid');

        self::assertInstanceOf(OperatorView::class, $view);
        self::assertSame('op-uuid', $view->id);
        self::assertSame('op@example.com', $view->email);
    }

    #[Test]
    public function itReturnsNullWhenNotFound(): void
    {
        $this->connection->method('fetchAssociative')->willReturn(false);

        self::assertNull($this->finder->find('op-uuid'));
    }
}
