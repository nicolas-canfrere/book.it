<?php
declare(strict_types=1);

namespace App\Tests\Room\Application\Service;

use App\Room\Application\Exception\InvalidCsvFormatException;
use App\Room\Application\Service\CsvRoomNumbersParser;
use App\Room\Application\Service\RoomCsvRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group('unit')]
final class CsvRoomNumbersParserTest extends TestCase
{
    private CsvRoomNumbersParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CsvRoomNumbersParser();
    }

    #[Test]
    public function itParsesValidCsvAndReturnsRows(): void
    {
        $rows = $this->parser->parse($this->makeCsvFile("number,floor\n101,1\n102,2\n2A,-1\n"));

        self::assertCount(3, $rows);
        self::assertSame('101', $rows[0]->number);
        self::assertSame(1, $rows[0]->floor);
        self::assertSame('102', $rows[1]->number);
        self::assertSame(2, $rows[1]->floor);
        self::assertSame('2A', $rows[2]->number);
        self::assertSame(-1, $rows[2]->floor);
    }

    #[Test]
    public function itReturnsEmptyArrayForHeaderOnlyCsv(): void
    {
        $rows = $this->parser->parse($this->makeCsvFile("number,floor\n"));

        self::assertSame([], $rows);
    }

    #[Test]
    public function itAcceptsNegativeAndZeroFloors(): void
    {
        $rows = $this->parser->parse($this->makeCsvFile("number,floor\n101,0\n102,-5\n"));

        self::assertSame(0, $rows[0]->floor);
        self::assertSame(-5, $rows[1]->floor);
    }

    #[Test]
    public function itThrowsWhenHeaderIsInvalid(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("number\n101\n"));
    }

    #[Test]
    public function itThrowsWhenFloorIsNotAnInteger(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("number,floor\n101,abc\n"));
    }

    #[Test]
    public function itThrowsWhenFloorIsDecimal(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("number,floor\n101,1.5\n"));
    }

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rooms_') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'rooms.csv', 'text/csv', null, true);
    }
}
