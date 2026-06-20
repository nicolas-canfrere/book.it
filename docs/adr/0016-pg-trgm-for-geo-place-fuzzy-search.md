# ADR-0016 — pg_trgm for Geo Place fuzzy search

`Geo Place Search` must tolerate partial and misspelled input (e.g. "pari" matching "Paris"), unlike PostgreSQL full-text search (`tsvector`/`tsquery`), which matches whole words or explicit prefixes (`pari:*`) but not typos or substrings. We use the `pg_trgm` extension with a GIN trigram index on `name`/`asciiname` instead, ranking results by `similarity()`.

This is a deliberate deviation from full-text search used elsewhere in the codebase conceptually (e.g. catalogue filtering) — full-text is the more obvious default for a Postgres-backed search feature, but it does not fit the typeahead/typo-tolerant use case `Geo Place Search` requires.
