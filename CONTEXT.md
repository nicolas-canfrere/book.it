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
A string identifier assigned to a Room within a Hotel. Unique per Hotel. Any non-blank string up to 50 characters (e.g. "101", "2A", "Penthouse", "Suite #3").
_Avoid_: room id, room code

**Room Floor**:
The floor level of a Room within a Hotel building, expressed as a signed integer. Zero is ground floor; negative values denote basement levels. Valid range: -20 to 300.
_Avoid_: level, storey, story

**Room Registration**:
The act of declaring a new Room in a Hotel. Rejected if a Room with the same number already exists in that Hotel, or if the referenced Hotel does not exist.
_Avoid_: room creation, room addition

**Room Catalogue**:
A paginated list of all Rooms belonging to a given Hotel, sorted alphabetically by number.
_Avoid_: room list, room inventory

## Relationships

- A **Room** belongs to exactly one **Hotel** (referenced by Hotel id across context boundaries)
- A **Room** has exactly one **Room Number** and exactly one **Room Floor**
- A **Room Registration** produces exactly one **Room**, or raises a conflict if the **Room** number already exists in that **Hotel**
- A **Room Catalogue** always belongs to exactly one **Hotel**

---

**Booker**:
A registered individual (natural person) who makes and pays for Reservations and is the contractual and financial responsible party. A Booker is a persistent account, not a per-Reservation identity.
_Avoid_: customer, client, guest, user

**Booker Registration**:
The act of a natural person registering their own Booker account (self-registration). Rejected if a Booker with the same email already exists, or if the applicant is under 18 years of age.
_Avoid_: booker creation, account creation, sign-up

## Relationships

- A **Booker** is uniquely identified by their email address
- A **Booker** has a first name, last name, email, phone number, and date of birth
- A **Booker Registration** produces exactly one **Booker**, or raises a conflict if the email is already taken
- A **Booker Registration** is rejected if the date of birth indicates the applicant is under 18 years old

## Flagged ambiguities

- B2B (company) Bookers are explicitly out of scope for now — only natural persons are supported

---

**Blocked Period** *(Période de blocage)* :
Une plage de nuits consécutives pendant laquelle une Room est indisponible à la réservation. Exprimée par une date de check-in (inclusive) et une date de check-out (exclusive). Une Room est disponible par défaut — un Blocked Period en suspend explicitement la disponibilité.
_Avoid_: unavailability, closure, restriction, fermeture

**Check-in Date** :
La première nuit couverte par un Blocked Period (borne inclusive).
_Avoid_: start date, from date, début

**Check-out Date** :
Le lendemain de la dernière nuit couverte par un Blocked Period (borne exclusive). La Room est libre ce soir-là.
_Avoid_: end date, to date, fin

**Availability Check** *(Vérification de disponibilité)* :
Une requête booléenne — "cette Room est-elle disponible sur cette période ?" — qui répond vrai si aucun Blocked Period ne chevauche la plage demandée.
_Avoid_: availability query, dispo check

**Availability Calendar** *(Calendrier de disponibilité)* :
La liste de tous les Blocked Periods d'une Room sur une période donnée, destinée à l'affichage et à la gestion par l'opérateur.
_Avoid_: availability grid, calendar view, planning

## Relationships

- Une **Room** a zéro ou plusieurs **Blocked Periods**
- Deux **Blocked Periods** d'une même **Room** ne peuvent pas se chevaucher — toute tentative est rejetée
- Un **Blocked Period** est immuable après création — pour corriger, on le supprime et on en recrée un
- Un **Availability Check** porte sur une **Room** et une plage check-in/check-out
- Un **Availability Calendar** porte sur une **Room** et une fenêtre temporelle

---

**Base Rate**:
The default per-night price of a Room, expressed in EUR. Applied whenever no Rate Period covers the night in question. A Room without a Base Rate is not bookable.
_Avoid_: default price, standard rate, tarif de base

**Rate Period**:
A date range (check-in inclusive, check-out exclusive) assigned to a Room, carrying an explicit per-night price in EUR. Overrides the Base Rate for the nights it covers.
_Avoid_: seasonal rate, pricing rule, plage tarifaire

**Promotion**:
A date range (check-in inclusive, check-out exclusive) assigned to a Room, carrying a percentage discount applied on top of the current applicable price (Base Rate or Rate Period price). Independent of Rate Periods — a Promotion may overlap any Rate Period.
_Avoid_: discount, offer, reduction, rabais

**Pricing Quote**:
The result of a price enquiry for a Room over a stay period: a total amount and a per-night breakdown, computed night by night as (Base Rate or Rate Period price) × (1 − Promotion discount if active).
_Avoid_: price estimate, stay price, devis

**Cancellation Policy**:
A per-Room rule, configured by the operator alongside rates, defining refund eligibility when a Reservation is cancelled by the Booker. Expressed as a number of calendar days before the check-in date. Cancellations made strictly before that day threshold are fully refunded; cancellations on or after that threshold receive no refund. The operator may update or delete a Cancellation Policy at any time — changes only affect future Reservations.
_Avoid_: refund policy, cancellation rule, cancellation terms

**Cancellation Terms**:
A snapshot of the Cancellation Policy captured at Reservation creation time. Forms part of the contract between the Booker and the operator. Immutable for the lifetime of the Reservation — changes to the Room's Cancellation Policy have no effect on existing Reservations. Takes one of two explicit forms: either "always refundable" (when the Room had no Cancellation Policy at creation time), or "refundable if cancelled strictly before N calendar days before check-in" (when a policy was in effect).
_Avoid_: cancellation policy snapshot, refund terms

## Relationships

- A **Room** has at most one **Base Rate** (referenced by Room id across context boundaries)
- A **Room** has zero or more **Rate Periods**; no two **Rate Periods** for the same **Room** may overlap
- A **Room** has zero or more **Promotions**; no two **Promotions** for the same **Room** may overlap
- **Rate Periods** and **Promotions** are independent layers — a **Promotion** may overlap any **Rate Period**
- A **Pricing Quote** covers exactly one **Room** and one stay period; price is calculated night by night
- A **Room** with no **Base Rate** cannot produce a **Pricing Quote**
- A **Room** may have at most one **Cancellation Policy**; a Room with no Cancellation Policy is always fully refundable on cancellation
- A **Base Rate**, **Rate Period**, **Promotion**, and **Cancellation Policy** are all mutable after creation (unlike **Blocked Periods**, which are immutable)

## Flagged ambiguities

- "occasional price increase" (e.g. festival demand) was considered as a third layer ("Surge") — resolved: delete-and-recreate of the affected Rate Period is the intended workflow; operators need control over the exact price, not just a percentage increase.

---

**Reservation**:
A confirmed or pending booking of a Room by a Booker for a specific stay period. A Reservation captures a snapshot of the total price at creation time and tracks a lifecycle status.
_Avoid_: booking, order, stay

**Reservation Status**:
The current lifecycle state of a Reservation. Transitions: `pending` → `confirmed` or `cancelled`; `confirmed` → `cancelled`. No other transitions are allowed.
_Avoid_: reservation state, booking status

**Cancellation** *(by Booker)*:
The voluntary act of a Booker terminating their own Reservation before the check-in date. Produces a `ReservationCancelled` event carrying the `refundAmount` (total price or zero, determined by the Cancellation Terms). Not permitted on or after the check-in date.
_Avoid_: booking cancellation, cancel booking

**Revocation** *(by Operator)*:
The unilateral act of an operator terminating a Reservation. Always triggers a full refund regardless of the Cancellation Terms. Permitted at any time while the Reservation is not yet cancelled. Produces a `ReservationRevoked` event.
_Avoid_: operator cancellation, forced cancellation

**Stay Period**:
The date range of a Reservation, defined by a check-in date (inclusive) and a check-out date (exclusive). The check-out date must be strictly after the check-in date.
_Avoid_: reservation period, booking dates, stay dates

## Relationships (Reservation)

- A **Reservation** references exactly one **Room** and exactly one **Booker**
- A **Reservation** covers exactly one **Stay Period**
- A **Reservation** captures a **Total Price** snapshot at creation time (in EUR cents) — immutable after creation
- A **Reservation** captures a snapshot of the **Cancellation Policy** as **Cancellation Terms** at creation time — immutable for the lifetime of the Reservation
- Refund eligibility on cancellation is determined by the **Cancellation Terms** stored on the Reservation, not the current Room policy
- A **Reservation** records `cancelledAt` and `cancelledBy` (Booker or Operator) when cancelled or revoked
- A **Cancellation** (by Booker) is only permitted strictly before the check-in date
- A **Revocation** (by Operator) is permitted at any time while the Reservation is active
- `ReservationCreated` causes Availability to create a **Blocked Period** for the Stay Period; `ReservationCancelled` and `ReservationRevoked` cause Availability to remove it
