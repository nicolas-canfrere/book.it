<?php

declare(strict_types=1);

namespace App\Hotel\Domain\ValueObject;

enum HotelAmenity: string
{
    case Concierge = 'concierge';
    case RoomService = 'room_service';
    case Laundry = 'laundry';
    case AirportShuttle = 'airport_shuttle';
    case LuggageStorage = 'luggage_storage';
    case Restaurant = 'restaurant';
    case Bar = 'bar';
    case Pool = 'pool';
    case Spa = 'spa';
    case Sauna = 'sauna';
    case Gym = 'gym';
    case Jacuzzi = 'jacuzzi';
    case Playground = 'playground';
    case KidsClub = 'kids_club';
    case Babysitting = 'babysitting';
    case Parking = 'parking';
    case EvCharging = 'ev_charging';
    case Elevator = 'elevator';
    case WheelchairAccessible = 'wheelchair_accessible';
    case ConferenceRoom = 'conference_room';
    case BusinessCenter = 'business_center';
    case PetsAllowed = 'pets_allowed';
    case Garden = 'garden';
    case Terrace = 'terrace';
    case BeachAccess = 'beach_access';
    case SkiAccess = 'ski_access';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
