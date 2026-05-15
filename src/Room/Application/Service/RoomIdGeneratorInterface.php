<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

interface RoomIdGeneratorInterface
{
    public function generate(): string;
}
