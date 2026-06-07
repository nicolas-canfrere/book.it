<?php

declare(strict_types=1);

namespace App\Tests\Operator\Application\UseCase\AssignAdminRoleToOperator;

use App\Operator\Application\UseCase\AssignAdminRoleToOperator\AssignAdminRoleToOperatorCommand;
use App\Operator\Application\UseCase\AssignAdminRoleToOperator\AssignAdminRoleToOperatorCommandHandler;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AssignAdminRoleToOperatorCommandHandlerTest extends TestCase
{
    private ExternalAccountRegistrarInterface&MockObject $accountRegistrar;
    private AssignAdminRoleToOperatorCommandHandler $handler;

    protected function setUp(): void
    {
        $this->accountRegistrar = $this->createMock(ExternalAccountRegistrarInterface::class);
        $this->handler = new AssignAdminRoleToOperatorCommandHandler($this->accountRegistrar);
    }

    #[Test]
    public function itAssignsAdminRoleToOperator(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('assignAdminRole')
            ->with('operator-uuid');

        ($this->handler)(new AssignAdminRoleToOperatorCommand('operator-uuid'));
    }
}
