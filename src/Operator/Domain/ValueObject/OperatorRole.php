<?php

declare(strict_types=1);

namespace App\Operator\Domain\ValueObject;

enum OperatorRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';
}
