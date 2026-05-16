# CLAUDE.md

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **book.it** (956 symbols, 1921 relationships, 3 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/book.it/context` | Codebase overview, check index freshness |
| `gitnexus://repo/book.it/clusters` | All functional areas |
| `gitnexus://repo/book.it/processes` | All execution flows |
| `gitnexus://repo/book.it/process/{name}` | Step-by-step execution trace |

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->

## Branching Policy

**Never implement directly on `main`.** Before any planning or implementation work (including when using superpowers skills such as `writing-plans`, `executing-plans`, or `subagent-driven-development`), check the current branch with `git branch --show-current`. If on `main`, create a feature branch first:

```bash
git checkout -b feat/<short-description>
```

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

`make unit-test-quiet` filters by `--group=unit --group=integration`. Tests without a `#[Group]` attribute are silently skipped. Convention:
- `TestCase` (no Symfony kernel) → `#[Group('unit')]`
- `KernelTestCase` (needs Symfony kernel) → `#[Group('integration')]`
- Controller tests → `#[Group('functional')]`, require `make functional-test`

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

### Route requirements

Use constants from `Symfony\Component\Routing\Requirement\Requirement` instead of inline regex strings:

```php
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/resources/{id}', requirements: ['id' => Requirement::UUID_V4], methods: ['GET'])]
```

Named arguments in `#[Route]` must follow the constructor parameter order: `path, name, requirements, options, defaults, host, methods, schemes, condition, priority`.

### Query string parameters (`#[MapQueryString]`)

- Nelmio automatically reads `#[OA\Parameter]` from DTO properties — do **not** repeat them in the controller's `#[OA\Get(parameters: [...])]`.
- `#[MapQueryString]` returns **404** on validation failure by default. Always set `validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY` to stay consistent with `#[MapRequestPayload]` behavior.

## Service Configuration

### `_instanceof` block is required in every context YAML

Each context service file (e.g., `config/services/room.yaml`) **must** include the `_instanceof` block to tag command and query handlers on the Messenger buses:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
    _instanceof:
        App\Shared\Application\Bus\SyncCommandHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.command.bus}
        App\Shared\Application\Bus\SyncQueryHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.query.bus}
```

Without this block, handlers are silently not registered → `NoHandlerForMessageException` at runtime (500, no compile-time warning).

### Exception mappings go in `config/services/exceptions.yaml`

All `ExceptionProblemRegistry` `$map` entries are centralized in `config/services/exceptions.yaml` (imported last in `services.yaml`). Do **not** define `ExceptionProblemRegistry` in individual context YAML files — Symfony's DI container silently overwrites the service with the last definition it encounters, so any context-specific definition would erase all others.

## Testing Conventions

### Functional test helpers must accept `KernelBrowser` as a parameter

`static::getClient()` returns `AbstractBrowser|null` — PHPStan rejects calls on it. Pass `$client` explicitly to any helper method:

```php
// correct
private function registerHotelAndGetId(KernelBrowser $client): string { ... }

// wrong — static::getClient() can be null
private function registerHotelAndGetId(): string {
    $client = static::getClient();
    ...
}
```

## Coding Standards

PHP CS Fixer enforces `@Symfony` + `@PSR2` rules with these notable additions:
- `declare_strict_types` required on all files
- `void_return` enforced
- Short array/list syntax only
- No superfluous `else`/`elseif`/`return`
- Ordered imports and class elements
