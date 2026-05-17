# ADR-0006 — Pricing as a separate context

Room pricing lives in `src/Pricing/`, not inside `src/Room/`. Room describes a physical space (number, floor, hotel). Pricing manages its commercial value over time. These evolve independently: Room registration has no reason to know about rates or promotions, and the future Reservation context will query Pricing directly, not Room.

This follows the same pattern as ADR-0005 (Availability): the Pricing context references a Room by opaque UUID only, with no structural knowledge of the Room entity.

A Room without a configured Base Rate in Pricing is simply not bookable — Pricing is not mandatory at Room registration time.
