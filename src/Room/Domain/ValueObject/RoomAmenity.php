<?php

declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

enum RoomAmenity: string
{
    // Connectivity
    case Wifi = 'wifi';
    case Ethernet = 'ethernet';
    case Tv = 'tv';
    case Telephone = 'telephone';

    // Climate
    case AirConditioning = 'air_conditioning';
    case Heating = 'heating';
    case CeilingFan = 'ceiling_fan';
    case Fireplace = 'fireplace';

    // Bathroom
    case Bathtub = 'bathtub';
    case Shower = 'shower';
    case Jacuzzi = 'jacuzzi';
    case Hairdryer = 'hairdryer';
    case Bidet = 'bidet';

    // Kitchen
    case Minibar = 'minibar';
    case Kettle = 'kettle';
    case CoffeeMachine = 'coffee_machine';
    case Microwave = 'microwave';
    case Kitchenette = 'kitchenette';
    case Refrigerator = 'refrigerator';

    // Workspace
    case Desk = 'desk';
    case Safe = 'safe';

    // Bedding & storage
    case BlackoutCurtains = 'blackout_curtains';
    case Wardrobe = 'wardrobe';

    // Misc
    case Balcony = 'balcony';
    case Terrace = 'terrace';
    case Iron = 'iron';
    case WashingMachine = 'washing_machine';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
