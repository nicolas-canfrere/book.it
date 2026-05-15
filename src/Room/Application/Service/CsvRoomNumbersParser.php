<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\Exception\InvalidCsvFormatException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CsvRoomNumbersParser
{
    /** @return list<string> */
    public function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getPathname(), 'r');
        if (false === $handle) {
            throw new InvalidCsvFormatException('Could not read the uploaded file.');
        }

        $header = fgetcsv($handle, escape: '');
        if ($header !== ['number']) {
            fclose($handle);
            throw new InvalidCsvFormatException('Invalid CSV format: expected a single "number" header column.');
        }

        $numbers = [];
        while (false !== ($row = fgetcsv($handle, escape: ''))) {
            $numbers[] = $row[0] ?? '';
        }
        fclose($handle);

        return $numbers;
    }
}
