# book.it

A hotel booking platform. Hotels are registered by operators and made available for reservation by bookers.

## Domain

The platform is organized around the following bounded contexts:

- **Hotel** — Hotel registration and catalogue (name, address, uniqueness)
- **Room** — Room registration and catalogue per hotel
- **Availability** — Blocked periods, availability checks and calendar
- **Pricing** — Base rates, rate periods, promotions, pricing quotes, cancellation policies
- **Booker** — Booker self-registration (natural persons, 18+)
- **Reservation** — Reservation lifecycle (pending → confirmed / cancelled), price snapshot, cancellation terms

See [CONTEXT.md](CONTEXT.md) for the full ubiquitous language and domain model.

## Tech Stack

- **PHP 8.4** / **Symfony 8.0**
- **PostgreSQL 16**
- **RabbitMQ 4** via Symfony Messenger + AMQP transport
- **Doctrine ORM** with migrations
- **Docker** — all tooling runs inside containers; no local PHP runtime required

## Getting Started

```bash
# Create the shared Docker network (once)
docker network create bookit-nw

# Start all services
make up

# Install dependencies
make install

# Run database migrations
make migrate
```

The API is then available at `http://localhost` and the OpenAPI documentation at `http://localhost/api/doc`.

## Commands

All development tasks run via `make`. Run `make help` for the full list.

| Command                   | Description                                     |
|---------------------------|-------------------------------------------------|
| `make up`                 | Start all services                              |
| `make down`               | Stop all services                               |
| `make install`            | Install PHP dependencies                        |
| `make migrate`            | Run database migrations                         |
| `make generate-migration` | Generate a new migration from schema diff       |
| `make lint`               | Run PHPStan + PHP CS Fixer                      |
| `make apply-cs`           | Auto-fix coding style                           |
| `make test`               | Run all tests (unit + functional)               |
| `make unit-test`          | Run unit tests only                             |
| `make functional-test`    | Run functional/integration tests only           |
| `make openapi`            | Regenerate `openapi.yaml` from route attributes |

## API

The REST API follows [RFC 7807](https://www.rfc-editor.org/rfc/rfc7807) (`application/problem+json`) for all error responses. The OpenAPI specification is kept in [`openapi.yaml`](openapi.yaml).

## Architecture

The codebase follows **Domain-Driven Design** with a layered architecture (Domain / Application / Infrastructure) per bounded context. Dependency rules between layers are enforced by [deptrac](https://github.com/deptrac/deptrac).

## Development

```bash
# Run static analysis
make static-code-analysis

# Run all tests
make test

# Apply coding standards
make apply-cs
```

Coding standards enforce `@Symfony` + `@PSR2` rules via PHP CS Fixer. `declare(strict_types=1)` is required on all files.
