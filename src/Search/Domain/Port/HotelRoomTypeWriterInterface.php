<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface HotelRoomTypeWriterInterface
{
    public function updateStarRating(string $hotelId, ?int $starRating): void;

    /** @param string[] $amenities */
    public function updateHotelAmenities(string $hotelId, array $amenities): void;

    /** @param list<array{type: string, count: int}> $bedComposition */
    public function upsertRoomType(
        string $roomTypeId,
        string $hotelId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void;

    /** @param list<array{type: string, count: int}> $bedComposition */
    public function updateRoomType(
        string $roomTypeId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void;

    /** @param string[] $amenities */
    public function updateRoomAmenities(string $roomTypeId, array $amenities): void;

    public function deleteRoomType(string $roomTypeId): void;

    public function updateBaseRateByRoom(string $roomId, int $amountCents): void;
}
