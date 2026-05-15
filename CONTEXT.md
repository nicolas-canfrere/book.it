# book.it

A hotel booking platform. Hotels are registered by operators and made available for reservation.

## Language

**Hotel**:
A registered hospitality establishment, uniquely identified by its name and physical address.
_Avoid_: property, accommodation, establishment

**Address**:
The physical location of a Hotel, composed of street address, postal code, city, and country (ISO 3166-1 alpha-2). Two hotels with the same name are distinct if their addresses differ.
_Avoid_: location, place

**Hotel Registration**:
The act of declaring a new Hotel in the system. Rejected if a Hotel with the same name and address already exists.
_Avoid_: hotel creation, hotel addition

## Relationships

- A **Hotel** has exactly one **Address**
- A **Hotel Registration** produces exactly one **Hotel**, or raises a conflict if the **Hotel** already exists

## Example dialogue

> **Dev:** "What if the operator registers 'Hôtel Ibis' twice?"
> **Domain expert:** "If it's the same address, it's a duplicate — reject it. If it's a different city, it's two different hotels."

**Hotel Catalogue**:
A paginated, filterable list of all registered Hotels. Supports filtering by city and country. Sorted alphabetically by name. Public — no authentication required.
_Avoid_: hotel list, hotel search, hotel directory

## Flagged ambiguities

- "unique hotel" was initially defined by name alone — resolved: uniqueness is name + full address (street, postal code, city, country).

---

**Room**:
A physical guest room belonging to a Hotel, uniquely identified within that Hotel by its number.
_Avoid_: chamber, unit, space

**Room Number**:
A string identifier assigned to a Room within a Hotel. Unique per Hotel. May be alphanumeric (e.g. "101", "2A", "Penthouse").
_Avoid_: room id, room code

**Room Registration**:
The act of declaring a new Room in a Hotel. Rejected if a Room with the same number already exists in that Hotel, or if the referenced Hotel does not exist.
_Avoid_: room creation, room addition

**Room Catalogue**:
A paginated list of all Rooms belonging to a given Hotel, sorted alphabetically by number.
_Avoid_: room list, room inventory

## Relationships

- A **Room** belongs to exactly one **Hotel** (referenced by Hotel id across context boundaries)
- A **Room Registration** produces exactly one **Room**, or raises a conflict if the **Room** number already exists in that **Hotel**
- A **Room Catalogue** always belongs to exactly one **Hotel**
