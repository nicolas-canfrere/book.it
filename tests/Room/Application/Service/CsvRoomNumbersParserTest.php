<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\Service;

use App\Room\Application\Exception\InvalidCsvFormatException;
use App\Room\Application\Service\CsvRoomNumbersParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CsvRoomNumbersParserTest extends TestCase
{
    private const string ROOM_TYPE_ID = 'cccccccc-0000-4000-8000-000000000001';

    private CsvRoomNumbersParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CsvRoomNumbersParser();
    }

    #[Test]
    public function itParsesValidCsvAndReturnsRows(): void
    {
        $rows = $this->parser->parse($this->makeCsvFile(
            "number,floor,roomTypeId\n101,1,".self::ROOM_TYPE_ID."\n102,2,".self::ROOM_TYPE_ID."\n2A,-1,".self::ROOM_TYPE_ID."\n"
        ));

        self::assertCount(3, $rows);
        self::assertSame('101', $rows[0]->number);
        self::assertSame(1, $rows[0]->floor);
        self::assertSame(self::ROOM_TYPE_ID, $rows[0]->roomTypeId);
        self::assertSame('102', $rows[1]->number);
        self::assertSame(2, $rows[1]->floor);
        self::assertSame('2A', $rows[2]->number);
        self::assertSame(-1, $rows[2]->floor);
    }

    #[Test]
    public function itReturnsEmptyArrayForHeaderOnlyCsv(): void
    {
        $rows = $this->parser->parse($this->makeCsvFile("number,floor,roomTypeId\n"));

        self::assertSame([], $rows);
    }

    #[Test]
    public function itAcceptsNegativeAndZeroFloors(): void
    {
        $rows = $this->parser->parse($this->makeCsvFile(
            "number,floor,roomTypeId\n101,0,".self::ROOM_TYPE_ID."\n102,-5,".self::ROOM_TYPE_ID."\n"
        ));

        self::assertSame(0, $rows[0]->floor);
        self::assertSame(-5, $rows[1]->floor);
    }

    #[Test]
    public function itThrowsWhenHeaderIsInvalid(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("number,floor\n101,1\n"));
    }

    #[Test]
    public function itThrowsWhenFloorIsNotAnInteger(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("number,floor,roomTypeId\n101,abc,".self::ROOM_TYPE_ID."\n"));
    }

    #[Test]
    public function itThrowsWhenFloorIsDecimal(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("number,floor,roomTypeId\n101,1.5,".self::ROOM_TYPE_ID."\n"));
    }

    private function makeCsvFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rooms_') . '.csv';
        file_put_contents($path, $content);

        return $path;
    }
}
