<?php

declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

enum BedType: string
{
    case Single = 'single';
    case Double = 'double';
    case Queen = 'queen';
    case King = 'king';
    case Bunk = 'bunk';
    case SofaBed = 'sofa_bed';
    case BabyCot = 'baby_cot';
}
