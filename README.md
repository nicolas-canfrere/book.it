# book.it

A hotel booking platform. Hotels are registered by operators and made available for reservation by bookers.

## Domain

The platform is organized around the following bounded contexts:

- **Hotel** — Hotel registration and catalogue (name, address, uniqueness)
- **Room** — Room registration and catalogue per hotel
- **Availability** — Blocked periods, availability holds, availability checks and calendar
- **Pricing** — Base rates, rate periods, promotions, pricing quotes, cancellation policies
- **Booker** — Booker self-registration (natural persons, 18+)
- **Reservation** — Reservation lifecycle (pending → confirmed / cancelled / expired), price snapshot, cancellation terms, expiration and revocation
- **Payment** — Payment processing webhooks, confirmation and cancellation handling
- **Notification** — Transactional emails (booking confirmation) dispatched asynchronously via Messenger

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

## Commands

All development tasks run via `make`. Run `make help` for the full list.

| Command                   | Description                                     |
|---------------------------|-------------------------------------------------|
| `make up`                 | Start all services                              |
| `make down`               | Stop all services                               |
| `make install`            | Install PHP dependencies                        |
| `make migrate`            | Run database migrations                         |
| `make generate-migration` | Generate a new migration from schema diff       |
| `make lint`               | Run PHPStan + PHP CS Fixer + deptrac            |
| `make apply-cs`           | Auto-fix coding style                           |
| `make test`               | Run all tests (unit + functional)               |
| `make unit-test`          | Run unit tests only                             |
| `make functional-test`    | Run functional/integration tests only           |
| `make openapi`            | Regenerate `openapi.yaml` from route attributes |

## API

The REST API follows [RFC 7807](https://www.rfc-editor.org/rfc/rfc7807) (`application/problem+json`) for all error responses. The OpenAPI specification is kept in [`openapi.yaml`](openapi.yaml).

## Architecture

The codebase follows **Domain-Driven Design** with a hexagonal architecture per bounded context. Each context (`Hotel`, `Room`, `Availability`, `Pricing`, `Booker`) is structured into four layers:

| Layer | Role |
|---|---|
| `UI` | HTTP controllers — entry points only |
| `Application` | Use cases, command/query factories |
| `Domain` | Entities, value objects, domain ports (interfaces) |
| `Infrastructure` | Doctrine repositories, external service adapters |

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
