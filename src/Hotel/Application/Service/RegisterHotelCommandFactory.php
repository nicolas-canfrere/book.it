<?php

declare(strict_types=1);

namespace App\Hotel\Application\Service;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Domain\Model\Address;
use Psr\Clock\ClockInterface;

final readonly class RegisterHotelCommandFactory
{
    public function __construct(
        private HotelIdGeneratorInterface $hotelIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        string $name,
        string $streetAddress,
        string $postalCode,
        string $city,
        string $country,
    ): RegisterHotelCommand {
        return new RegisterHotelCommand(
            $this->hotelIdGenerator->generate(),
            $name,
            new Address($streetAddress, $postalCode, $city, $country),
            $this->clock->now(),
        );
    }
}
