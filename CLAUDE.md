# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Tech Stack

- **PHP 8.4** / **Symfony 8.0**
- **PostgreSQL 16** (connection name: `bookit`, env var: `BOOKIT_DATABASE_URL`)
- **RabbitMQ 4** via Symfony Messenger + AMQP transport
- **Doctrine ORM** with migrations
- All tooling runs inside Docker — there is no local PHP runtime assumed

## Commands

All commands run via `make`. Run `make help` to see the full list.

### Development

```bash
make install          # Install dependencies via Dockerized Composer
make up               # Start all services (creates bookit-nw Docker network)
make down             # Stop all services
make migrate          # Run pending Doctrine migrations
make generate-migration  # Generate a new migration file
make php-cli          # Shell into the PHP container
```

### Code Quality

```bash
make lint             # Run PHPStan + PHP CS Fixer (full analysis)
make static-code-analysis  # PHPStan only (level: max)
make apply-cs         # PHP CS Fixer only
```

### Tests

```bash
make unit-test-quiet  # Run unit + integration tests with summarized output
make unit-test-quiet ARGS="--filter TestClassName"  # Run a single test or filter
make functional-test  # Run functional tests (spins up full Docker stack + DB)
make functional-test ARGS="--filter TestClassName"  # Filter functional tests
make test             # Run unit + integration + functional (full suite)
```

PHPUnit is configured with `DAMA\DoctrineTestBundle` — each test wraps DB operations in a transaction that is rolled back, so the database is reset between tests without truncation.

Controller tests use `#[Group('functional')]` and require `make functional-test`, not `make unit-test-quiet`.

## Error Handling

All HTTP error responses follow RFC 7807 (`application/problem+json`). A centralized `ProblemDetailExceptionListener` in `Shared/Infrastructure/Http/` handles all exceptions.

To map a new domain exception to a typed Problem Detail, add an entry to the context's service config (e.g., `config/services/hotel.yaml`):

```yaml
App\Shared\Infrastructure\Http\ExceptionProblemRegistry:
    arguments:
        $map:
            App\SomeContext\Domain\Exception\SomeException:
                type: 'https://book.it/problems/some-problem'
                title: 'Some Problem'
                status: 409
```

Unmapped exceptions fall back to `"type": "about:blank"` with a 500 status. Validation errors (422) automatically include a `violations` array with per-field messages.

OpenAPI schemas for error responses (`ProblemDetail`, `ValidationProblemDetail`) are defined in `config/packages/nelmio_api_doc.yaml` and referenced by `$ref` string in controller annotations.

When working on any API route, always keep the OpenAPI documentation up to date:
- Add or update `#[OA\...]` attributes on the controller (request body, responses, status codes)
- Add or update shared schemas in `config/packages/nelmio_api_doc.yaml` if the route introduces new reusable response shapes
- Run `make openapi` to regenerate `openapi.yaml` and verify there are no warnings

## Coding Standards

PHP CS Fixer enforces `@Symfony` + `@PSR2` rules with these notable additions:
- `declare_strict_types` required on all files
- `void_return` enforced
- Short array/list syntax only
- No superfluous `else`/`elseif`/`return`
- Ordered imports and class elements
