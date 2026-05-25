<?php

declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

final readonly class BedComposition
{
    /** @param list<BedEntry> $entries */
    public function __construct(
        public array $entries,
    ) {
        if ([] === $entries) {
            throw new \InvalidArgumentException('Bed composition must contain at least one entry.');
        }
    }

    /** @return list<array{type: string, count: int}> */
    public function toArray(): array
    {
        return array_map(
            fn(BedEntry $e) => ['type' => $e->type->value, 'count' => $e->count],
            $this->entries,
        );
    }

    /** @param list<array{type: string, count: int}> $data */
    public static function fromArray(array $data): self
    {
        return new self(array_map(
            fn(array $entry) => new BedEntry(BedType::from($entry['type']), $entry['count']),
            array_values($data),
        ));
    }
}
