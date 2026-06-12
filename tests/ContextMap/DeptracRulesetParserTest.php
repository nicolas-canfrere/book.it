<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\DeptracRulesetParser;

#[Group('unit')]
class DeptracRulesetParserTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/deptrac_' . uniqid() . '.yaml';
        file_put_contents($this->tmpFile, <<<'YAML'
deptrac:
    ruleset:
        Reservation:
            - ReservationContract
            - AvailabilityContract
            - BookerContract
            - Shared
            - Vendor
        Hotel:
            - HotelContract
            - Shared
            - Vendor
        Notification:
            - BookerContract
            - Shared
            - Vendor
        ReservationContract: ~
        HotelContract: ~
        BookerContract: ~
        Shared:
            - Vendor
        Vendor: ~
YAML);
    }

    protected function tearDown(): void
    {
        unlink($this->tmpFile);
    }

    #[Test]
    public function itExtractsConsumedContexts(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);
        self::assertSame(['Availability', 'Booker'], $result['Reservation']);
    }

    #[Test]
    public function itSkipsOwnContract(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);
        self::assertNotContains('Reservation', $result['Reservation']);
    }

    #[Test]
    public function itReturnsEmptyArrayForContextWithNoConsumedContracts(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);
        self::assertSame([], $result['Hotel']);
    }

    #[Test]
    public function itSkipsContractLayers(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);
        self::assertArrayNotHasKey('ReservationContract', $result);
        self::assertArrayNotHasKey('HotelContract', $result);
    }

    #[Test]
    public function itHandlesContextWithoutOwnContractLayer(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);
        self::assertArrayHasKey('Notification', $result);
        self::assertSame(['Booker'], $result['Notification']);
    }
}
