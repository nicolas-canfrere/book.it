# book.it

A hotel booking platform. Hotels are registered by operators and made available for reservation.

## Language

**Hotel**:
A registered hospitality establishment, uniquely identified by its name and physical address.
_Avoid_: property, accommodation, establishment

**Address**:
The physical location of a Hotel, composed of street address, postal code, city, and country (ISO 3166-1 alpha-2). May optionally reference a **Geo Place** by its GeoNames id, captured via Geo Place Search, to disambiguate the free-text city. Two hotels with the same name are distinct if their addresses differ.
_Avoid_: location, place

**Hotel Registration**:
The act of declaring a new Hotel in the system. Rejected if a Hotel with the same name and address already exists. A Star Rating may optionally be provided at registration time.
_Avoid_: hotel creation, hotel addition

**Star Rating**:
An optional operator-declared classification of a Hotel according to the European Hotelstars Union scale. Composed of a number of stars (integer, 1–5) and a Superior distinction (boolean). Self-declared by the operator — the system performs no external validation. A Hotel without a Star Rating is valid and fully usable. The Superior distinction is only valid when a number of stars is present; removing the stars implicitly removes the Superior distinction.
_Avoid_: HSU classification, hotel category, hotel stars

**Star Rating Classification**:
The act of setting or updating the Star Rating of a Hotel after registration. The operator may add, modify, or remove the classification at any time.
_Avoid_: star update, rating update

## Relationships

- A **Hotel** has exactly one **Address**
- An **Address** may reference at most one **Geo Place** (optional, via its GeoNames id)
- A **Hotel** has at most one **Star Rating** (optional)
- A **Hotel** has zero or more **Hotel Amenities** (optional at registration)
- A **Hotel Registration** produces exactly one **Hotel**, or raises a conflict if the **Hotel** already exists
- A **Star Rating Classification** may be performed at any time after **Hotel Registration**
- A **Star Rating** with `superior: true` requires a number of stars to be present; removing the stars implicitly sets `superior` to false
- A **Hotel Amenity Declaration** replaces the full **Hotel Amenity** list and may be performed at any time after **Hotel Registration**

## Example dialogue

> **Dev:** "What if the operator registers 'Hôtel Ibis' twice?"
> **Domain expert:** "If it's the same address, it's a duplicate — reject it. If it's a different city, it's two different hotels."

> **Dev:** "Can an operator declare '5 étoiles Superior' without proof?"
> **Domain expert:** "Yes — it's self-declared. The operator is responsible for accuracy, like the name or address."

**Hotel Catalogue**:
A paginated, filterable list of all registered Hotels. Supports filtering by city, country, minimum Star Rating (`minStars`), and Hotel Amenities. Sorted alphabetically by name. Public — no authentication required.
_Avoid_: hotel list, hotel search, hotel directory

**Hotel Amenity**:
A facility available to all guests of a Hotel, drawn from a fixed predefined enum (e.g. `pool`, `parking`, `playground`, `gym`). A Hotel has zero or more Hotel Amenities. Hotel Amenities are optional at Hotel Registration and replaceable at any time via a Hotel Amenity Declaration.
_Avoid_: hotel feature, hotel service, équipement hôtel

**Hotel Amenity Declaration**:
The act of replacing the full list of Hotel Amenities on a Hotel. The operator submits the complete new list — any previously declared amenity not in the new list is removed.
_Avoid_: amenity update, amenity addition, amenity removal

## Flagged ambiguities

- "unique hotel" was initially defined by name alone — resolved: uniqueness is name + full address (street, postal code, city, country).
- `superior` with no stars is not a valid state — the domain rejects it; removing stars implicitly clears `superior`.

---

**Room**:
A physical guest room belonging to a Hotel, uniquely identified within that Hotel by its number. Every Room belongs to exactly one Room Type.
_Avoid_: chamber, unit, space

**Room Number**:
A string identifier assigned to a Room within a Hotel. Unique per Hotel. Any non-blank string up to 50 characters (e.g. "101", "2A", "Penthouse", "Suite #3").
_Avoid_: room id, room code

**Room Floor**:
The floor level of a Room within a Hotel building, expressed as a signed integer. Zero is ground floor; negative values denote basement levels. Valid range: -20 to 300.
_Avoid_: level, storey, story

**Room Registration**:
The act of declaring a new Room in a Hotel. Rejected if a Room with the same number already exists in that Hotel, if the referenced Hotel does not exist, or if the referenced Room Type does not exist in that Hotel.
_Avoid_: room creation, room addition

**Room Catalogue**:
A paginated list of all Rooms belonging to a given Hotel, sorted alphabetically by number. Operator-facing — used to manage the physical room inventory.
_Avoid_: room list, room inventory

**Room Type Catalogue**:
A paginated, filterable list of all Room Types belonging to a given Hotel. Supports filtering by Room Amenities. Booker-facing — the primary view for discovering what types of accommodation a Hotel offers. Distinct from the Room Catalogue, which lists individual physical Rooms for operators.
_Avoid_: room list, room type list, room search

**Room Amenity**:
A feature present in all Rooms of a given Room Type, drawn from a fixed predefined enum (e.g. `tv`, `wifi`, `air_conditioning`, `minibar`, `jacuzzi`). A Room Type has zero or more Room Amenities. A value may appear in both enums with a different meaning — `jacuzzi` at hotel level means a shared facility; at room type level it means a private in-room jacuzzi. Room Amenities are optional at Room Type Registration and replaceable at any time via a Room Type Amenity Declaration.
_Avoid_: room feature, room equipment, équipement chambre

**Room Type Amenity Declaration**:
The act of replacing the full list of Room Amenities on a Room Type. The operator submits the complete new list — any previously declared amenity not in the new list is removed.
_Avoid_: amenity update, amenity addition, amenity removal

**Room Type**:
A named category defined by a Hotel operator, describing the physical characteristics shared by all Rooms of that type: living space count, surface area, bed composition, guest capacity, and accessibility. A Room Type is scoped to a single Hotel — two hotels may use the same name independently. A Room Type name is unique within a Hotel.
_Avoid_: room category, room class, chambre type

**Room Type Registration**:
The act of declaring a new Room Type in a Hotel. Rejected if a Room Type with the same name already exists in that Hotel.
_Avoid_: room type creation, room type addition

**Living Space Count**:
The number of distinct living spaces (bedroom, living room, study, etc.) in a Room Type, excluding bathrooms and toilets. Expressed as an integer between 1 and 20. Convention follows the French real-estate standard: a studio is 1, a suite with a separate lounge is 2.
_Avoid_: room count, piece count, number of rooms

**Bed Type**:
An enumerated kind of bed available in a Room Type: `single` (~90 cm), `double` (~140 cm), `queen` (~160 cm), `king` (~180 cm+), `bunk` (bunk beds), `sofa_bed` (sofa bed), `baby_cot` (travel cot).
_Avoid_: lit, bed kind, bed category

**Bed Composition**:
The list of `{BedType, count}` entries describing the beds in a Room Type. Must contain at least one entry. Each count is between 1 and 10.
_Avoid_: bed list, lit configuration, bed setup

**Guest Capacity**:
The maximum number of guests a Room Type can accommodate, expressed as an integer between 1 and 20. Declared explicitly by the operator — not derived from the Bed Composition.
_Avoid_: max occupancy, occupancy, capacity

## Relationships

- A **Room** belongs to exactly one **Hotel** (referenced by Hotel id across context boundaries)
- A **Room** has exactly one **Room Number** and exactly one **Room Floor**
- A **Room** belongs to exactly one **Room Type** — mandatory at registration, reassignable after
- A **Room Registration** produces exactly one **Room**, or raises a conflict if the **Room** number already exists in that **Hotel**
- A **Room Catalogue** always belongs to exactly one **Hotel** (operator view — lists individual Rooms)
- A **Room Type Catalogue** always belongs to exactly one **Hotel** (Booker view — lists Room Types, filterable by Room Amenity)
- A **Room Type** belongs to exactly one **Hotel**
- A **Room Type** name is unique within a **Hotel**
- A **Room Type** has exactly one **Living Space Count**, one **Guest Capacity**, one **Bed Composition**, and one **Accessibility** flag; surface area is optional
- A **Room Type** has zero or more **Room Amenities** (optional at registration)
- A **Room Type** may be deleted only if no **Room** is currently assigned to it
- A **Room Type** attributes are mutable after creation
- A **Room Type Registration** produces exactly one **Room Type**, or raises a conflict if the name already exists in that **Hotel**
- A **Room Type Amenity Declaration** replaces the full **Room Amenity** list and may be performed at any time after **Room Type Registration**

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

**Operator**:
A registered professional account representing a person who manages one or more Hotels on the platform. Created by an administrator — not self-registered. An Operator is a persistent account, not a per-Hotel identity.
_Avoid_: manager, admin, hotel owner, staff

**Operator Registration**:
The act of an administrator creating an Operator account. Rejected if an Operator with the same email already exists. No age requirement applies.
_Avoid_: operator creation, account creation

## Relationships

- An **Operator** is uniquely identified by their email address
- An **Operator** has a first name, last name, email, and phone number
- An **Operator Registration** produces exactly one **Operator**, or raises a conflict if the email is already taken
- The association between an **Operator** and a **Hotel** is established separately, after **Operator Registration**

## Flagged ambiguities

- Operator endpoint authentication is deferred — the registration endpoint is currently unprotected; a Keycloak `admin` role guard is planned for a later iteration

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

**Availability Hold** *(Blocage temporaire)* :
Une réservation temporaire de disponibilité posée sur une Room pour une Stay Period, au moment où le Booker engage le processus de paiement. Expire automatiquement au bout de 15 minutes si la Reservation n'est pas confirmée. Empêche toute nouvelle Reservation ou tout nouvel Availability Hold sur la même Room pour une période chevauchante pendant sa durée de vie.
_Avoid_: soft block, temporary lock, hold, blocage

**Availability Check** *(Vérification de disponibilité)* :
Une requête booléenne — "cette Room est-elle disponible sur cette période ?" — qui répond vrai si aucun Blocked Period et aucun Availability Hold actif ne chevauche la plage demandée.
_Avoid_: availability query, dispo check

**Availability Calendar** *(Calendrier de disponibilité)* :
La liste de tous les Blocked Periods d'une Room sur une période donnée, destinée à l'affichage et à la gestion par l'opérateur.
_Avoid_: availability grid, calendar view, planning

## Relationships

- Une **Room** a zéro ou plusieurs **Blocked Periods**
- Deux **Blocked Periods** d'une même **Room** ne peuvent pas se chevaucher — toute tentative est rejetée
- Un **Blocked Period** est immuable après création — pour corriger, on le supprime et on en recrée un
- Une **Room** a zéro ou plusieurs **Availability Holds** actifs ; deux **Availability Holds** sur la même **Room** ne peuvent pas se chevaucher
- Un **Availability Hold** est lié à exactement une **Reservation** (par reservation_id, référence cross-context)
- Un **Availability Hold** expire automatiquement 15 minutes après sa création s'il n'est pas converti en **Blocked Period**
- Un **Availability Hold** est converti en **Blocked Period** à la confirmation de la **Reservation** (paiement autorisé)
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

**Night Price**:
A single night's pricing detail within a Pricing Quote or Price Breakdown: the calendar date, the applicable rate before discount (`rateAmountCents`), the discount percentage if a Promotion was active (`discountPercent`, nullable), and the effective amount charged (`effectiveAmountCents`).
_Avoid_: night rate, nightly price, night entry

**Pricing Quote**:
The result of a price enquiry for a Room over a stay period: a total amount and a list of Night Prices, computed night by night as (Base Rate or Rate Period price) × (1 − Promotion discount if active).
_Avoid_: price estimate, stay price, devis

**Price Breakdown**:
A snapshot of the Pricing Quote's Night Price list captured at Reservation creation time. Immutable for the lifetime of the Reservation — changes to rates or promotions have no effect on existing Reservations. Stored alongside the Total Price to support detailed invoicing without querying the Pricing context.
_Avoid_: night breakdown, price detail, pricing snapshot

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
- A **Pricing Quote** covers exactly one **Room** and one stay period; it produces one **Night Price** per calendar night
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
The current lifecycle state of a Reservation. Transitions: `pending` → `confirmed` (Payment Confirmation), `pending` → `cancelled` (Payment Abandonment or Expiration), `confirmed` → `cancelled` (Cancellation or Revocation), `confirmed` → `checked_in` (Check-in), `checked_in` → `checked_out` (Check-out). No other transitions are allowed.
_Avoid_: reservation state, booking status

**Cancellation** *(by Booker)*:
The voluntary act of a Booker terminating their own Reservation before the check-in date. Produces a `ReservationCancelled` event carrying the `refundAmount` (total price or zero, determined by the Cancellation Terms). Not permitted on or after the check-in date.
_Avoid_: booking cancellation, cancel booking

**Revocation** *(by Operator)*:
The unilateral act of an operator terminating a Reservation. Always triggers a full refund regardless of the Cancellation Terms. Permitted at any time while the Reservation is not yet cancelled. Produces a `ReservationRevoked` event.
_Avoid_: operator cancellation, forced cancellation

**Expiration** *(by System)*:
The automatic termination of a `pending` Reservation by the system when its Availability Hold reaches its expiry time without having been confirmed. Distinct from Cancellation (Booker-initiated), Revocation (Operator-initiated), and Payment Abandonment (provider-initiated). Produces a `ReservationExpired` event. The associated Availability Hold is deleted at the same time.
_Avoid_: revocation, cancellation, timeout, expiry

**Payment Confirmation** *(by Payment Provider)*:
The external notification from the payment provider that a payment attempt succeeded. Triggers the transition `pending → confirmed` on the Reservation. Produces a `ReservationConfirmed` event, which causes Availability to convert the Availability Hold into a Blocked Period.
_Avoid_: payment success, payment approval

**Payment Failure** *(by Payment Provider)*:
The external notification from the payment provider that a payment attempt failed (e.g. card declined, insufficient funds). The Reservation remains `pending` — the Booker may retry with another payment method until the Availability Hold expires. Produces no domain transition.
_Avoid_: payment error, payment rejection

**Payment Abandonment** *(by Booker)*:
The act of a Booker leaving the payment flow without completing payment (e.g. closing the page, clicking back). Notified by the payment provider via a cancel webhook. Triggers the transition `pending → cancelled` on the Reservation. Produces a `ReservationPaymentCancelled` event, which causes Availability to delete the Availability Hold.
_Avoid_: payment cancel, payment cancellation, booking abandonment

**Stay Period**:
The date range of a Reservation, defined by a check-in date (inclusive) and a check-out date (exclusive). The check-out date must be strictly after the check-in date.
_Avoid_: reservation period, booking dates, stay dates

**Guest**:
A person occupying the Room during the Stay Period. Distinct from the Booker — the Booker may or may not be a Guest. A Guest is identified by first name, last name, and date of birth. A Guest is not a persistent account; they exist only in the context of a Reservation. The Booker may pre-register Guests after Reservation creation and before the check-in date (optional). The operator registers Guests on-site at Check-in time.
_Avoid_: companion, occupant, traveller, passenger

**Guest Count**:
The declared number of persons who will occupy the Room, provided by the Booker at Reservation creation time. Must not exceed the Guest Capacity of the Room Type.
_Avoid_: number of guests, occupancy count, pax

**Check-in** *(by Operator)*:
The act of the operator registering the Guests physically present on arrival, transitioning the Reservation from `confirmed` to `checked_in`. The operator records the actual Guests at this point.
_Avoid_: arrival, guest registration, check in

**Check-out** *(by Operator)*:
The act of the operator recording the physical departure of Guests, transitioning the Reservation from `checked_in` to `checked_out`. The operator provides an explicit `actualDepartureDate`, which may be before the planned Check-out Date (early departure) but not after. Permitted from the Check-in Date (inclusive) to the Check-out Date (inclusive). Produces a `ReservationCheckedOut` event carrying `reservationId`, `roomId`, `bookerId`, `checkIn`, `checkOut`, and `actualDepartureDate`, which causes Availability to delete the Blocked Period.
_Avoid_: departure, guest departure, check out

## Relationships (Reservation)

- A **Reservation** references exactly one **Room** and exactly one **Booker**
- A **Reservation** covers exactly one **Stay Period**
- A **Reservation** carries exactly one **Guest Count**, declared at creation time and validated against the **Guest Capacity** of the Room Type
- A **Reservation** has zero or more **Guests**, pre-registered by the Booker (optional) or registered by the operator at **Check-in**
- A **Check-in** transitions a `confirmed` Reservation to `checked_in`; only permitted on or after the check-in date
- A **Check-out** transitions a `checked_in` Reservation to `checked_out`; permitted between the Check-in Date (inclusive) and the Check-out Date (inclusive); forbidden after the Check-out Date
- A **Cancellation** (by Booker) is forbidden once the Reservation is `checked_in`
- A **Reservation** captures a **Total Price** snapshot at creation time (in EUR cents) — immutable after creation
- A **Reservation** captures a **Price Breakdown** snapshot at creation time — immutable for the lifetime of the Reservation
- A **Reservation** captures a snapshot of the **Cancellation Policy** as **Cancellation Terms** at creation time — immutable for the lifetime of the Reservation
- Refund eligibility on cancellation is determined by the **Cancellation Terms** stored on the Reservation, not the current Room policy
- A **Reservation** records `cancelledAt` and `cancelledBy` (Booker or Operator) when cancelled or revoked
- A **Cancellation** (by Booker) is only permitted strictly before the check-in date
- A **Revocation** (by Operator) is permitted at any time while the Reservation is active
- `ReservationCreated` causes Availability to create an **Availability Hold** for the Stay Period; the Reservation context simultaneously schedules an `ExpireReservation` delayed message (15 min)
- `ReservationConfirmed` causes Availability to convert the **Availability Hold** into a **Blocked Period**
- `ReservationExpired` causes Availability to delete the **Availability Hold**
- `ReservationPaymentCancelled` causes Availability to delete the **Availability Hold** (Payment Abandonment path — symmetric to Expiration)
- `ReservationCancelled` and `ReservationRevoked` cause Availability to remove the **Blocked Period** (only applicable to confirmed Reservations)
- `ReservationCheckedOut` causes Availability to delete the **Blocked Period**; carries `reservationId`, `roomId`, `bookerId`, `checkIn`, `checkOut`, and `actualDepartureDate`
- An **Expiration** only applies to a `pending` Reservation — if the Reservation was already confirmed when the delayed message arrives, it is a no-op

---

**Booking Confirmation Notification**:
The communication sent to a Booker when their Reservation transitions to `confirmed`. Informs the Booker that their stay is registered. Delivered asynchronously — the Booker may receive it with a short delay after payment confirmation.
_Avoid_: confirmation email, booking email, notification mail

**Notification**:
A communication dispatched to a Booker in response to a Reservation lifecycle event. A Notification is fire-and-forget — no delivery record is persisted; failures are logged and retried by the messaging infrastructure. Currently limited to the **Booking Confirmation Notification** channel (email).
_Avoid_: message, alert, communication

## Relationships (Notification)

- A **Booking Confirmation Notification** is triggered by exactly one `ReservationConfirmed` event
- A **Booking Confirmation Notification** is addressed to the **Booker** referenced by the confirmed **Reservation**
- A **Notification** carries no persistent state — delivery outcome is observable only via logs

---

**Geo Place**:
A geographic reference record imported from the GeoNames open dataset, identifying a real-world city or town by its GeoNames id, name, country (ISO 3166-1 alpha-2), and administrative subdivision code (state/region, raw GeoNames `admin1code` — not the resolved name). Distinct from the `city` field on a Hotel's **Address**, which remains free text.
_Avoid_: location, place, city (when referring to the referential entity)

**Geo Place Search**:
A fuzzy, typeahead-style lookup of **Geo Places** by free-text input (e.g. "pari" matching "Paris"), used to power autocomplete in search and registration forms. Requires at least 2 characters of input; returns at most 10 candidates ranked by text similarity. Public — no authentication required.
_Avoid_: geo search, place autocomplete, city search

## Relationships (Geo)

- A **Geo Place** is uniquely identified by its GeoNames id
- A **Geo Place Search** returns zero or more **Geo Places** ranked by similarity to the input text
