<?php

declare(strict_types=1);

namespace App\Tests\Operator\UI\Console;

use App\Operator\Application\Service\RegisterOperatorCommandFactory;
use App\Operator\Application\UseCase\AssignAdminRoleToOperator\AssignAdminRoleToOperatorCommand;
use App\Operator\Application\UseCase\RegisterOperator\RegisterOperatorCommand;
use App\Operator\Domain\Port\OperatorIdGeneratorInterface;
use App\Operator\UI\Console\RegisterAdminOperatorCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\ValueObject\OperatorId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('unit')]
final class RegisterAdminOperatorCommandTest extends TestCase
{
    private OperatorIdGeneratorInterface&MockObject $idGenerator;
    private ClockInterface&MockObject $clock;
    private SyncCommandBusInterface&MockObject $commandBus;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->idGenerator = $this->createMock(OperatorIdGeneratorInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->commandBus = $this->createMock(SyncCommandBusInterface::class);

        $factory = new RegisterOperatorCommandFactory($this->idGenerator, $this->clock);

        $this->tester = new CommandTester(
            new RegisterAdminOperatorCommand($factory, $this->commandBus),
        );
    }

    #[Test]
    public function itDispatchesRegisterThenAssignAdminRole(): void
    {
        $now = new \DateTimeImmutable();
        $this->idGenerator->expects(self::once())->method('generate')->willReturn(new OperatorId('uuid-1'));
        $this->clock->expects(self::once())->method('now')->willReturn($now);

        $dispatchedCommands = [];
        $this->commandBus->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(static function (object $cmd) use (&$dispatchedCommands): void {
                $dispatchedCommands[] = $cmd;
            });

        $this->tester->execute([
            'firstName' => 'Alice',
            'lastName' => 'Martin',
            'email' => 'alice@hotel.com',
            'phone' => '+33612345678',
            'password' => 'SecurePass123!',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertInstanceOf(RegisterOperatorCommand::class, $dispatchedCommands[0]);
        self::assertEquals(new OperatorId('uuid-1'), $dispatchedCommands[0]->id);
        self::assertSame('Alice', $dispatchedCommands[0]->firstName);
        self::assertSame('Martin', $dispatchedCommands[0]->lastName);
        self::assertSame('alice@hotel.com', $dispatchedCommands[0]->email);
        self::assertSame('+33612345678', $dispatchedCommands[0]->phone);
        self::assertSame('SecurePass123!', $dispatchedCommands[0]->password);
        self::assertSame($now, $dispatchedCommands[0]->registeredAt);
        self::assertInstanceOf(AssignAdminRoleToOperatorCommand::class, $dispatchedCommands[1]);
        self::assertEquals(new OperatorId('uuid-1'), $dispatchedCommands[1]->operatorId);
        $display = $this->tester->getDisplay();
        self::assertStringContainsString('alice@hotel.com', $display);
        self::assertStringContainsString('uuid-1', $display);
    }
}
