# Context Map

> Generated — do not edit manually. Run: `make contextmap`

```mermaid
graph LR
  Availability -->|RoomFinderInterface, RoomsByTypeFinderInterface| Room
  Booker -->|AccountRegistrarInterface| Security
  Hotel -->|GeoPlaceCheckerInterface| Geo
  Notification -->|BookerFinderInterface| Booker
  Notification -->|ReservationFinderInterface| Reservation
  Operator -->|AccountRegistrarInterface| Security
  Pricing -->|RoomFinderInterface, RoomsByTypeFinderInterface| Room
  Reservation -->|AvailabilityCheckerInterface| Availability
  Reservation -->|BookerFinderInterface| Booker
  Reservation -->|CancellationPolicyFinderInterface, PricingQuoteCalculatorInterface, PricingQuoteFinderInterface| Pricing
  Reservation -->|RoomFinderInterface, RoomsByTypeFinderInterface| Room
  Room -->|HotelFinderInterface| Hotel
  Security -->|OperatorFinderInterface| Operator
```
