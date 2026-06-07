# Room Type Catalogue as the Booker-facing view, distinct from Room Catalogue

The Room Catalogue (individual Rooms by number) is an operator tool for managing physical inventory. Bookers have no use for a list of room numbers — they choose accommodation by type and features. We therefore introduce a separate **Room Type Catalogue** as the Booker-facing view, listing Room Types filterable by Room Amenity.

## Considered Options

- **Single Room Catalogue for both audiences** — expose individual Rooms to Bookers, filtered by Room Type attributes. Rejected: a list of room numbers is meaningless to a Booker and forces client-side deduplication by type for any amenity filter.
- **Room Catalogue lists Room Types** — repurpose the existing concept. Rejected: operators need individual Rooms (number, floor, type assignment) for inventory management; conflating the two audiences into one view creates a model that serves neither well.

## Consequences

The Room context now exposes two read models: Room Catalogue (operator) and Room Type Catalogue (Booker). Room Amenity filtering belongs exclusively to the Room Type Catalogue.
