# ADR 0018 — Non-nullable types on required DTO properties for accurate OpenAPI generation

## Status
Accepted

## Context
Request DTOs annotated with `#[MapQueryString]` or `#[MapRequestPayload]` were declaring required fields as `?type = null`:

```php
public function __construct(
    public ?string $name = null,
    public ?string $city = null,
    // ...
) {}
```

These fields had `#[Assert\NotBlank]` constraints and were logically mandatory, but Nelmio ApiDocBundle generated them as `required: false` and `nullable: true` in `openapi.yaml`. The generated spec did not match the actual API contract.

The root cause: **Nelmio derives `required` and `nullable` exclusively from the PHP type system**, not from `#[OA\Property(required: true)]` or Symfony validation constraints. A nullable type with a default value always produces `required: false, nullable: true`, regardless of any assertion.

This pattern appeared across all contexts: `Search`, `Geo`, `Availability`, `Pricing`, `Reservation`, `Room`, `Booker`, `Operator`, `Hotel`.

## Decision
Required DTO properties must be declared as non-nullable types without default values:

```php
public function __construct(
    public string $name,   // required: true in OpenAPI
    public int $guests,    // required: true in OpenAPI
    public ?int $surfaceM2 = null, // genuinely optional — stays nullable, placed last
) {}
```

The rule: **the PHP type is the OpenAPI contract**. If a field is required by the domain, declare it non-nullable and without a default. Trust the framework to handle missing fields: both `#[MapQueryString]` and `#[MapRequestPayload]` use `COLLECT_DENORMALIZATION_ERRORS`, which collects deserialization errors and returns a 422 response without needing a default value.

Optional parameters with defaults (`?type = null`) must be placed **after** all required parameters in the constructor to satisfy PHP's parameter ordering rules.

## Alternatives considered
- **Keep `?type = null`, override Nelmio with `#[OA\Property(required: true, nullable: false)]`** — works for individual properties but is fragile: the override is silently ineffective for the `required` field at the schema level (Nelmio re-derives it from the type), and must be duplicated on every property. Rejected: fighting the framework.
- **Keep `?type = null`, add custom Nelmio describer** — possible but unnecessary complexity. The PHP type system already encodes the intent precisely. Rejected.

## Consequences
- `openapi.yaml` accurately reflects the required/optional contract of every endpoint.
- `PartialDenormalizationException` fires before the Symfony validator on `#[MapRequestPayload]` DTOs when fields are missing. As a result, a field that is present-but-invalid will not appear in the same 422 response alongside a missing-field error. This is accepted: the 422 still surfaces all missing fields as deserialization errors, and present-but-invalid fields are caught on subsequent requests. Do not revert to nullable types to avoid this behaviour.
- Defensive null guards in controllers (`?? ''`, `?? throw new \LogicException(...)`) become dead code after the migration. PHPStan flags them as `nullCoalesce.property` errors — remove them.
- Nelmio generates unused schema components for `#[MapQueryString]` DTOs as a side-effect of introspection. These are registered in `.redocly.lint-ignore.yaml` under `no-unused-components` to suppress linter false positives.
