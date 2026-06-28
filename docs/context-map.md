# Context Map

> Generated — do not edit manually. Run: `make contextmap`

```mermaid
graph LR
  Availability -->|RoomFinderInterface, RoomsByTypeFinderInterface| Room
  Booker -->|AccountRegistrarInterface| Security
  Hotel -->|GeoPlaceCheckerInterface| Geo
  Notification -->|BookerFinderInterface| Booker
  Notification -->|ReservationFinderInterface| Reservation
  Onboarding -->|OrganizationCheckerInterface, OrganizationRegistrarInterface| Organization
  Onboarding -->|OperatorFinderInterface, OwnerOperatorRegistrarInterface| Operator
  Operator -->|AccountRegistrarInterface| Security
  Pricing -->|RoomFinderInterface, RoomsByTypeFinderInterface| Room
  Reservation -->|AvailabilityCheckerInterface| Availability
  Reservation -->|BookerFinderInterface| Booker
  Reservation -->|BaseRateFinderInterface, CancellationPolicyFinderInterface, PricingQuoteFinderInterface| Pricing
  Reservation -->|RoomFinderInterface, RoomsByTypeFinderInterface| Room
  Room -->|HotelFinderInterface| Hotel
  Room -->|BaseRateFinderInterface, CancellationPolicyFinderInterface, PricingQuoteFinderInterface| Pricing
  Security -->|OperatorFinderInterface, OwnerOperatorRegistrarInterface| Operator
```
