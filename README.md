# book.it

A hotel booking platform. Hotels are registered by operators and made available for reservation by bookers.

## Domain

The platform is organized around the following bounded contexts:

- **Hotel** — Hotel registration and catalogue (name, address, uniqueness)
- **Room** — Room registration and catalogue per hotel
- **Availability** — Blocked periods, availability holds, availability checks and calendar
- **Pricing** — Base rates, rate periods, promotions, pricing quotes, cancellation policies
- **Booker** — Booker self-registration (natural persons, 18+)
- **Operator** — Operator account registration (administrator-created, manages one or more hotels)
- **Reservation** — Reservation lifecycle (pending → confirmed / cancelled / expired), price snapshot, cancellation terms, expiration and revocation
- **Payment** — Payment processing webhooks, confirmation and cancellation handling
- **Notification** — Transactional emails (booking confirmation) dispatched asynchronously via Messenger
- **Geo** — GeoNames-backed geographic places and typeahead place search
- **Search** — Read-optimized room/availability search index, built from cross-context domain events
- **Security** — Keycloak-backed authentication, account registration, and identity mapping
- **Translation** — Per-locale translations for translatable subjects (e.g. amenities)

See [CONTEXT.md](CONTEXT.md) for the full ubiquitous language and domain model.

## Tech Stack

- **PHP 8.4** / **Symfony 8.0**
- **PostgreSQL 16**
- **RabbitMQ 4** via Symfony Messenger + AMQP transport
- **Doctrine ORM** with migrations
- **Mailpit** — local mail catcher for development (SMTP on port 1025, web UI on port 8025)
- **Docker** — all tooling runs inside containers; no local PHP runtime required

## Getting Started

```bash
# Install dependencies
make install

# Start all services (creates the Docker network and runs migrations automatically)
make up
```

The API is then available at `http://localhost` and the OpenAPI documentation at `http://localhost/api/doc`.

The mail catcher (Mailpit) is available at `http://localhost:8025`.

## Importing Geo Places

The `Geo` context can be populated from a [GeoNames](https://download.geonames.org/export/dump/) cities dump (e.g. `cities500.txt`, `cities1000.txt`) via the `geo:import-places` console command:

```bash
make exec CMD="bin/console geo:import-places /app/var/cities1000.txt"
```

(adjust the path to wherever the dump file is mounted/copied inside the `php` container).

The dump is a large tab-separated file (hundreds of thousands of lines), inserted one row at a time via `ON CONFLICT ... DO UPDATE`. In the `dev` environment, Symfony's debug mode (`APP_DEBUG=1`) keeps every executed query plus its backtrace in memory for the whole process (`profiling_collect_backtrace: '%kernel.debug%'` in `config/packages/doctrine.yaml`), which exhausts the container's memory limit on a dump this size. Run the import with debug mode off to avoid this:

```bash
docker compose run --rm -e APP_DEBUG=0 php sh -c "bin/console cache:clear && bin/console geo:import-places /app/var/cities1000.txt"
```

`cache:clear` is required because the container cache is normally compiled with debug mode on; building a debug-off container needs its own compile pass first.

## Commands

All development tasks run via `make`. Run `make help` for the full list.

| Command                      | Description                                                              |
|------------------------------|--------------------------------------------------------------------------|
| `make up`                    | Start all services                                                       |
| `make down`                  | Stop all services                                                        |
| `make install`               | Install PHP dependencies                                                 |
| `make fixtures`              | Load fixtures (truncates all tables first)                               |
| `make migrate`               | Run database migrations                                                  |
| `make generate-migration`    | Generate a new migration from schema diff                                |
| `make lint`                  | Full analysis: PHP CS Fixer (fix) + PHPStan + Deptrac + OpenAPI lint     |
| `make lint-ci`               | Same as `lint` but checks CS without auto-fixing (for CI)                |
| `make static-code-analysis`  | PHPStan only                                                             |
| `make deptrac`               | Architecture layer check only (layers + bounded context boundaries)      |
| `make apply-cs`              | Auto-fix coding style with PHP CS Fixer                                  |
| `make check-cs`              | Check coding style without auto-fixing (dry-run)                         |
| `make lint-openapi`          | Lint `openapi.yaml` with Redocly CLI                                     |
| `make test`                  | Run all tests (unit + functional)                                        |
| `make unit-test`             | Run unit tests only                                                      |
| `make functional-test`       | Run functional/integration tests only                                    |
| `make generate-docs`         | Regenerate `openapi.yaml`, `domainevents.yaml`, and `contextmap.yaml`    |
| `make openapi`               | Regenerate `openapi.yaml` from route attributes                          |
| `make events`                | Regenerate `domainevents.yaml` from registered listeners                 |
| `make contextmap`            | Regenerate `contextmap.yaml` and `docs/context-map.md` from source       |
| `make contextmap-check`      | Validate `contextmap.yaml` against source code                           |

## Documentation

| File                                               | Content                                                                                                                     |
|----------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------|
| [`openapi.yaml`](openapi.yaml)                     | REST API specification — routes, request/response schemas. Regenerate with `make openapi`.                                  |
| [`asyncapi.yaml`](asyncapi.yaml)                   | Async messaging specification — RabbitMQ commands, producers, consumers, and payloads. Updated manually.                    |
| [`domainevents.yaml`](domainevents.yaml)           | Domain event catalogue — all events with their properties and listeners per bounded context. Regenerate with `make events`. |
| [`contextmap.yaml`](contextmap.yaml)               | Context map — bounded contexts, their relationships, and integration patterns. Regenerate with `make contextmap`.           |
| [`docs/context-map.md`](docs/context-map.md)       | Human-readable context map rendered from `contextmap.yaml`. Regenerate with `make contextmap`.                              |

The REST API follows [RFC 7807](https://www.rfc-editor.org/rfc/rfc7807) (`application/problem+json`) for all error responses. Interactive API documentation is available at `http://localhost/api/doc` when the stack is running.

## Architecture

The codebase follows **Domain-Driven Design** with a hexagonal architecture per bounded context. Each context (e.g. `Hotel`, `Room`, `Availability`, `Pricing`, `Booker`, `Search`) is structured into up to four layers:

| Layer            | Role                                               |
|------------------|----------------------------------------------------|
| `UI`             | HTTP controllers — entry points only               |
| `Application`    | Use cases, command/query factories                 |
| `Domain`         | Entities, value objects, domain ports (interfaces) |
| `Infrastructure` | Doctrine repositories, external service adapters   |

A `Shared` context holds cross-cutting concerns (bus interfaces, HTTP error handling).

Dependency rules are enforced by [deptrac](https://github.com/deptrac/deptrac) (`make deptrac`):

- **UI** → Application, Domain, Shared, any vendor
- **Application** → Domain, Shared, PSR interfaces only (no Symfony/Doctrine)
- **Domain** → Shared only — no framework dependencies
- **Infrastructure** → Domain, Shared, any vendor

## Development

```bash
# Full code analysis (PHPStan + CS Fixer + deptrac)
make lint

# Run all tests
make test
```

Coding standards enforce `@Symfony` + `@PSR2` rules via PHP CS Fixer. `declare(strict_types=1)` is required on all files.

## Contributing

**Direct commits to `main` are forbidden.** All changes go through a branch and a Pull Request.

```bash
git checkout -b feat/<short-description>   # new feature
git checkout -b fix/<short-description>    # bug fix
git checkout -b docs/<short-description>   # documentation only
git checkout -b refactor/<short-description>
```

Before opening a PR, ensure `make lint` and `make test` pass.
