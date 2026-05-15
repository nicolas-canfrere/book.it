# API errors follow RFC 7807 Problem Details via a centralized Kernel listener

All HTTP error responses from the API return `application/problem+json` (RFC 7807). A single `KernelEvents::EXCEPTION` listener intercepts all exceptions and maps them to Problem Details responses. Known domain exceptions (e.g., `HotelAlreadyExistsException`) are mapped to typed URIs (`https://book.it/problems/…`); generic HTTP errors use `"type": "about:blank"`. Validation errors (422) include a `violations` extension with per-field messages.

## Considered options

- **Per-controller try/catch** — rejected: duplicates mapping logic across every future controller and risks inconsistency as the API grows.
- **Exception interface (`HttpProblemException`)** — domain exceptions would implement `getType()`, `getTitle()`, `getStatus()`. Rejected: couples domain exceptions to HTTP concerns, violating the layering already established (see ADR-0001).
- **Third-party library** (`phpro/api-problem-bundle`, etc.) — rejected: the need is precise and bounded; a library adds maintenance overhead without meaningful benefit over ~100 lines of infrastructure code.

## Consequences

The exception-to-Problem Details mapping lives in an infrastructure registry. Adding a new domain exception requires a corresponding entry in that registry to produce a typed URI; unmapped exceptions fall back to `"type": "about:blank"` with a 500 status.
