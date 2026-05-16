<?php

declare(strict_types=1);

namespace App\Booker\Domain\Exception;

final class BookerUnderageException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Booker must be at least 18 years old.');
    }
}
