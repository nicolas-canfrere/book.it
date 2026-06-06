<?php

declare(strict_types=1);

namespace App\Translation\Domain\ValueObject;

enum SubjectType: string
{
    case Hotel = 'hotel';
    case RoomType = 'room_type';
}
