<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\Service;

use App\Room\Application\Exception\InvalidCsvFormatException;
use App\Room\Application\Service\CsvRoomNumbersParser;
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
    public function itParsesValidCsvAndReturnsNumbers(): void
    {
        $numbers = $this->parser->parse($this->makeCsvFile("number\n101\n102\n2A\n"));

        self::assertSame(['101', '102', '2A'], $numbers);
    }

    #[Test]
    public function itReturnsEmptyArrayForHeaderOnlyCsv(): void
    {
        $numbers = $this->parser->parse($this->makeCsvFile("number\n"));

        self::assertSame([], $numbers);
    }

    #[Test]
    public function itThrowsWhenHeaderIsInvalid(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("room_number\n101\n"));
    }

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rooms_') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'rooms.csv', 'text/csv', null, true);
    }
}
