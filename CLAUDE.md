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
make unit-test-quiet  # Run tests with summarized output
make unit-test-quiet ARGS="--filter TestClassName"  # Run a single test or filter
```

PHPUnit is configured with `DAMA\DoctrineTestBundle` — each test wraps DB operations in a transaction that is rolled back, so the database is reset between tests without truncation.

## Coding Standards

PHP CS Fixer enforces `@Symfony` + `@PSR2` rules with these notable additions:
- `declare_strict_types` required on all files
- `void_return` enforced
- Short array/list syntax only
- No superfluous `else`/`elseif`/`return`
- Ordered imports and class elements
