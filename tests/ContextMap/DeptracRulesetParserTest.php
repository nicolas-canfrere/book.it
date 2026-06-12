<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\DeptracRulesetParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeptracRulesetParserTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'deptrac');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    #[Test]
    public function itParsesSimpleRuleset(): void
    {
        $yaml = <<<'EOF'
deptrac:
    ruleset:
        Hotel:
            - HotelContract
            - RoomContract
        Room:
            - RoomContract
EOF;
        file_put_contents($this->tmpFile, $yaml);

        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertArrayHasKey('Hotel', $result);
        self::assertArrayHasKey('Room', $result);
        self::assertSame(['Room'], $result['Hotel']);
        self::assertSame([], $result['Room']);
    }

    #[Test]
    public function itSkipsContextsWithoutDependencies(): void
    {
        $yaml = <<<'EOF'
deptrac:
    ruleset:
        Hotel: null
        Room:
            - RoomContract
EOF;
        file_put_contents($this->tmpFile, $yaml);

        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertArrayNotHasKey('Hotel', $result);
    }

    #[Test]
    public function itSkipsContractLayers(): void
    {
        $yaml = <<<'EOF'
deptrac:
    ruleset:
        HotelContract:
            - RoomContract
        Hotel:
            - RoomContract
EOF;
        file_put_contents($this->tmpFile, $yaml);

        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertArrayNotHasKey('HotelContract', $result);
        self::assertArrayHasKey('Hotel', $result);
    }

    #[Test]
    public function itFiltersNonContractDependencies(): void
    {
        $yaml = <<<'EOF'
deptrac:
    ruleset:
        Hotel:
            - Room
            - RoomContract
            - SomethingElse
EOF;
        file_put_contents($this->tmpFile, $yaml);

        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertSame(['Room'], $result['Hotel']);
    }

    #[Test]
    public function itSkipsOwnContextContract(): void
    {
        $yaml = <<<'EOF'
deptrac:
    ruleset:
        Hotel:
            - HotelContract
            - RoomContract
EOF;
        file_put_contents($this->tmpFile, $yaml);

        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertSame(['Room'], $result['Hotel']);
    }

    #[Test]
    public function itExcludesConfiguredLayers(): void
    {
        $yaml = <<<'EOF'
deptrac:
    ruleset:
        Hotel:
            - RoomContract
        Shared:
            - HotelContract
        Notification:
            - HotelContract
        Vendor:
            - HotelContract
EOF;
        file_put_contents($this->tmpFile, $yaml);

        $result = (new DeptracRulesetParser())->parse($this->tmpFile, ['Shared', 'Vendor', 'Notification']);

        self::assertArrayNotHasKey('Notification', $result);
        self::assertArrayNotHasKey('Shared', $result);
        self::assertArrayNotHasKey('Vendor', $result);
        self::assertArrayHasKey('Hotel', $result);
    }
}
