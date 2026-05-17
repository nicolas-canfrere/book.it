<?php

declare(strict_types=1);

namespace App\Hotel\Application\Service;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Port\HotelIdGeneratorInterface;
use Psr\Clock\ClockInterface;

final readonly class RegisterHotelCommandFactory
{
    public function __construct(
        private HotelIdGeneratorInterface $hotelIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        ?string $name,
        ?string $streetAddress,
        ?string $postalCode,
        ?string $city,
        ?string $country,
    ): RegisterHotelCommand {
        if (null === $name || null === $streetAddress || null === $postalCode || null === $city || null === $country) {
            throw new \InvalidArgumentException('All hotel fields are required.');
        }

        return new RegisterHotelCommand(
            $this->hotelIdGenerator->generate(),
            $name,
            new Address($streetAddress, $postalCode, $city, $country),
            $this->clock->now(),
        );
    }
}
