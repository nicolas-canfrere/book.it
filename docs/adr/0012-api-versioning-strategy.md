# API versioning uses URL path prefixes managed centrally in route config

All API routes are versioned via a URL path prefix (`/api/v{n}/`). The prefix is declared once in `config/routes.yaml` and never repeated in controller `#[Route]` attributes. Version-specific controllers live under `UI/Http/Api/V{n}/`; unchanged endpoints fall back to the previous version's controllers via a secondary route import. Unversioned requests (`/api/hotels`) receive a 307 redirect to the current version. Deprecated versions advertise their removal date via standard HTTP headers before returning 410 Gone.

## Considered options

**Versioning strategy**

- **URL path prefix** (`/api/v1/`) — chosen: explicit in logs and browser tools, supported natively by Nelmio/OpenAPI areas, no special client support required.
- **`Accept` header** (`application/vnd.bookit.v2+json`) — rejected: opaque in logs, browser-unfriendly, Nelmio support is limited.
- **Query parameter** (`?version=2`) — rejected: hard to cache at proxy level and awkward to document.

**Prefix location**

- **Centralised in `config/routes.yaml`** — chosen: controllers are version-agnostic; adding a new version requires no controller changes.
- **Hardcoded in each `#[Route]`** — rejected: every controller must be touched for each new version, and the prefix cannot be varied per environment.

**Breaking-change use cases**

- **Version-suffixed query names** (`GetBookerV2Query`) — rejected: leaks HTTP versioning concerns into the Application layer, violating the layered architecture.
- **Intent-based query names** (`GetBookerWithReservationsQuery`) — chosen: the Application layer exposes capabilities; the UI layer decides which capability each version calls.

**Fallback for unchanged routes**

- **Duplicate controllers per version** — rejected: mechanical copy-paste with no functional difference.
- **Route import with `name_prefix`** — chosen: V2 overrides are declared first (Symfony first-match wins); all remaining V1 routes are re-exposed under `/api/v2` automatically via a second import with a `v2_fallback_` name prefix.

## Route config example (V1 + V2)

```yaml
# config/routes.yaml
controllers:
    resource: routing.controllers
    prefix: /api
```

```yaml
# config/routes/api_v1.yaml
api_v1:
    resource: ../../src/
    type: attribute
    prefix: /api/v1
    path: '**/Http/Api/*.php'        # excludes the V2 subdirectory
```

```yaml
# config/routes/api_v2.yaml

# V2-specific controllers — matched first (Symfony first-match wins)
api_v2_overrides:
    resource: ../../src/
    type: attribute
    prefix: /api/v2
    path: '**/Http/Api/V2/*.php'

# All V1 controllers re-exposed under /api/v2 as fallback
api_v2_fallback:
    resource: ../../src/
    type: attribute
    prefix: /api/v2
    path: '**/Http/Api/*.php'
    name_prefix: v2_fallback_
```

```yaml
# config/routes/nelmio.yaml
app.swagger_ui_v1:
    path: /api/doc/v1
    methods: GET
    defaults: { _controller: nelmio_api_doc.controller.swagger_ui, area: v1 }

app.swagger_ui_v2:
    path: /api/doc/v2
    methods: GET
    defaults: { _controller: nelmio_api_doc.controller.swagger_ui, area: v2 }
```

```yaml
# config/packages/nelmio_api_doc.yaml
nelmio_api_doc:
    areas:
        v1:
            path_patterns: ['^/api/v1']
        v2:
            path_patterns: ['^/api/v2']
```

```yaml
# config/services/shared.yaml
parameters:
    app.api.current_version: 'v2'
    app.api.deprecated_versions:
        v1: '2026-06-01'            # Deprecation headers added until this date
                                    # 410 Gone returned from this date onward
```

## Consequences

- Adding a new API version requires: a new route import block in `config/routes.yaml`, a `UI/Http/Api/V{n}/` directory for breaking-change controllers, and a Nelmio area entry.
- The `ApiVersionRedirectListener` (priority 33, before `RouterListener`) handles both the 307 redirect for unversioned requests and the 410 Gone response for past-sunset deprecated versions. Doc routes (`/api/doc*`) are excluded from redirect logic.
- Deprecating a version requires adding it to `app.api.deprecated_versions` in `config/services/shared.yaml` with a sunset date. The `ApiDeprecationResponseListener` then adds `Deprecation`, `Sunset`, `Link`, and `X-API-Version` headers automatically until the sunset date is reached.
- Use cases and domain logic are never versioned — only the UI layer (controllers + response mappers) varies per version.
