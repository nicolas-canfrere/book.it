# CLAUDE.md

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **book.it** (6243 symbols, 13575 relationships, 51 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

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

## Implementation Planning

See skill `writing-plans` for the context-gathering protocol. It defines a minimal, ordered protocol (CLAUDE.md → CONTEXT.md → architecture map → GitNexus → max 3 canonical files) that avoids broad codebase exploration before writing a plan.

## Branching Policy

**Committing directly to `main` is forbidden.** Every change — feature, fix, refactor, or documentation — must go through a branch and a Pull Request.

Before any planning or implementation work (including when using superpowers skills such as `writing-plans`, `executing-plans`, or `subagent-driven-development`), check the current branch with `git branch --show-current`. If on `main`, create a branch first:

```bash
git checkout -b feat/<short-description>   # new feature
git checkout -b fix/<short-description>    # bug fix
git checkout -b docs/<short-description>   # documentation only
git checkout -b refactor/<short-description>
```

Once work is complete: push the branch and open a PR — never push to `main` directly. Use the `superpowers:finishing-a-development-branch` skill to guide this step.

## Tech Stack

- **PHP 8.4** / **Symfony 8.0**
- **PostgreSQL 16** (connection name: `bookit`, env var: `BOOKIT_DATABASE_URL`)
- **RabbitMQ 4** via Symfony Messenger + AMQP transport
- **Doctrine ORM** with migrations
- All tooling runs inside Docker — there is no local PHP runtime assumed

## Architecture

Each bounded context (examples: `Hotel`, `Room`, `Availability`, `Pricing`, `Booker`) has four layers:

| Layer            | Namespace pattern               | Allowed dependencies                    |
|------------------|---------------------------------|-----------------------------------------|
| `UI`             | `App\{Context}\UI\`             | Application, Domain, Shared, any vendor |
| `Application`    | `App\{Context}\Application\`    | Domain, Shared, `Psr\*` only            |
| `Domain`         | `App\{Context}\Domain\`         | Shared only — no framework              |
| `Infrastructure` | `App\{Context}\Infrastructure\` | Domain, Shared, any vendor              |

- Domain port interfaces (e.g. `*RepositoryInterface`, `*IdGeneratorInterface`) live in `Domain\Port\`, **not** `Application\Service\`
- `Shared` (`App\Shared\`) is a cross-cutting context — usable by all layers
- Architecture rules are enforced by deptrac: `make deptrac` (also runs as part of `make lint`)

## Commands

All commands run via `make`. Run `make help` for the full list.

Important for test and code analysis use make commands !

| Target                      | Purpose                                            |
|-----------------------------|----------------------------------------------------|
| `make test`                 | Run all tests (unit + functional)                  |
| `make unit-test`            | Unit tests only (no DB)                            |
| `make functional-test`      | Functional/integration tests (needs DB)            |
| `make lint`                 | Full analysis: CS Fixer + PHPStan + Deptrac        |
| `make static-code-analysis` | PHPStan only                                       |
| `make deptrac`              | Architecture layer check only                      |
| `make apply-cs`             | Auto-fix coding standards                          |
| `make openapi`              | Regenerate `openapi.yaml` from route/OA attributes |
| `make generate-migration`   | Generate a new Doctrine migration                  |
| `make migrate`              | Run pending migrations                             |
| `make up` / `make down`     | Start / stop Docker services                       |

## Reference Documentation

| File | Content | Regenerate |
|------|---------|------------|
| `openapi.yaml` | HTTP REST API spec (routes, request/response schemas) | `make openapi` |
| `asyncapi.yaml` | Async messaging spec (RabbitMQ commands, operations, payloads) | manual |
| `domainevents.yaml` | Domain event catalogue — all events with properties and listeners per context | `make events` |

Consult these files to understand existing contracts before adding or changing routes, async commands, or domain events.

## Error Handling

All HTTP errors follow RFC 7807 (`application/problem+json`) via `ProblemDetailExceptionListener` in `Shared/Infrastructure/Http/`.

Map domain exceptions in `config/services/exceptions.yaml` (centralized — never in context YAMLs):

```yaml
App\Shared\Infrastructure\Http\ExceptionProblemRegistry:
    arguments:
        $map:
            App\SomeContext\Domain\Exception\SomeException:
                type: 'https://book.it/problems/some-problem'
                title: 'Some Problem'
                status: 409
```

- Unmapped exceptions → `"type": "about:blank"`, 500
- Validation errors (422) auto-include a `violations` array
- Always run `make openapi` after adding/changing routes; keep `#[OA\...]` attributes up to date

### Routes

- Use `Requirement::UUID_V4` (not inline regex) for UUID route params
- `#[Route]` named args order: `path, name, requirements, options, defaults, host, methods, schemes, condition, priority`
- `#[MapQueryString]`: always set `validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY`
- Nelmio reads `#[OA\Parameter]` from DTO properties — don't repeat in `#[OA\Get(parameters: [...])]`

## Service Configuration

See skill `symfony-service-config` for details. Key rules:

- **`_instanceof` required in every context YAML** — without it, handlers silently fail at runtime (`NoHandlerForMessageException`)
- **Never add `resource:` for directories that don't exist** — Symfony throws `FileLocatorFileNotFoundException` at container build time
- **Exception mappings → `config/services/exceptions.yaml` only** — context YAMLs silently overwrite each other
- **`#[AsMessageHandler]` is forbidden in the Application layer** — deptrac rejects Symfony vendor dependencies there. Implement `AsyncCommandHandlerInterface` on the handler — the `_instanceof` block in every context YAML maps it to `messenger.bus.default`. Do NOT use per-service `tags:` at the bottom of YAML: they are silently ignored with `APP_DEBUG=0` (the consumer's mode).
  ```yaml
  # In _instanceof of the context YAML:
  App\Shared\Application\Bus\AsyncCommandHandlerInterface:
      tags:
          - {name: messenger.message_handler, bus: messenger.bus.default}
  ```
- **DBAL `Connection::transactional()` expects a `Closure`, not `callable`** — PHPStan rejects a bare callable. Always wrap:
  ```php
  $this->connection->transactional(static function () use ($callback): void {
      $callback();
  });
  ```

## Testing

See skill `symfony-testing` for details. Key rules:

- `TestCase` → `#[Group('unit')]`, `KernelTestCase` → `#[Group('integration')]`, controllers → `#[Group('functional')]`
- Integration tests need a compilable DI container — create Doctrine repos before writing tests that use the handler
- Functional test helpers must accept `KernelBrowser $client` as a parameter (not `static::getClient()`)
- The `unit-test` Docker service has **no database** — integration tests that need persistence must use in-memory test doubles, not real Doctrine repos
- To generate a new migration: `make generate-migration` (not `make migration`)

### Messenger in tests

Any async dispatch via `AsyncCommandDispatcherInterface` will attempt a real AMQP connection in functional tests and return 500. Always override the transport in `config/packages/messenger.yaml`:

```yaml
when@test:
    framework:
        messenger:
            transports:
                commands: 'in-memory://'
```

## Coding Standards

PHP CS Fixer enforces `@Symfony` + `@PSR2` rules with these notable additions:
- `declare_strict_types` required on all files
- `void_return` enforced
- Short array/list syntax only
- No superfluous `else`/`elseif`/`return`
- Ordered imports and class elements
