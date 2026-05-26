# Hotel name/address normalization lives in infrastructure, not the domain

Hotel uniqueness is enforced on normalized forms of name and address (lowercase, ASCII transliteration via Symfony AsciiSlugger) to handle typos and accent variants. We chose to keep this normalization entirely in the infrastructure layer (the Doctrine repository), stored as computed slug columns, rather than exposing a `NormalizerInterface` port in the domain.

The domain expresses the invariant ("a Hotel must be unique by name and address") through a repository method and a `HotelAlreadyExistsException`. The *how* of normalization — which library, which algorithm — is an infrastructure detail that the domain has no business knowing about.

## Considered options

- **Domain port (`HotelNameNormalizerInterface`)** — the domain declares the need, infrastructure provides the Slugger. Rejected: adds an abstraction for a concern that is purely about string storage and comparison. Nothing in the domain logic depends on the normalized form; it only exists to protect the unique constraint.
- **PostgreSQL `unaccent` + `lower`** — normalize at the DB level with a function-based unique index. Viable, but moves behavior into the schema and away from application code, making it harder to test and reason about in PHP.

## Consequences

The Symfony AsciiSlugger is the de-facto normalization contract. Swapping it for another algorithm is a migration — normalized columns would need to be recomputed.
