<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Exception;

final class InvalidGeoPlaceException extends \DomainException
{
    public function __construct(string $geoPlaceId)
    {
        parent::__construct(\sprintf('Geo place "%s" does not exist in the Geo referential.', $geoPlaceId));
    }
}
