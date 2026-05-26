<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\Exception\InvalidCsvFormatException;

final readonly class CsvRoomNumbersParser
{
    /** @return list<RoomCsvRow> */
    public function parse(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new InvalidCsvFormatException('Could not read the uploaded file.');
        }

        $header = fgetcsv($handle, escape: '');
        if ($header !== ['number', 'floor', 'roomTypeId']) {
            fclose($handle);
            throw new InvalidCsvFormatException('Invalid CSV format: expected "number,floor,roomTypeId" header columns.');
        }

        $rows = [];
        while (false !== ($row = fgetcsv($handle, escape: ''))) {
            $rawFloor = trim($row[1] ?? '');
            $floor = filter_var($rawFloor, FILTER_VALIDATE_INT);
            if (false === $floor) {
                fclose($handle);
                throw new InvalidCsvFormatException(\sprintf('Invalid CSV format: floor value "%s" is not a valid integer.', $rawFloor));
            }
            $rows[] = new RoomCsvRow($row[0] ?? '', $floor, trim($row[2] ?? ''));
        }
        fclose($handle);

        return $rows;
    }
}
