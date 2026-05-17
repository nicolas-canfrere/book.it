---
name: writing-plans
description: "Use when writing an implementation plan for this project. Defines a minimal context-gathering protocol to avoid broad codebase exploration."
---

# Writing Implementation Plans — book.it

This skill overrides the default `writing-plans` behavior for this project. Its purpose is to gather exactly the context needed — no more.

## Context Protocol (follow in order, stop when you have enough)

### Step 1 — Already in context (read nothing)

CLAUDE.md is always loaded. It contains:
- Stack (PHP 8.4 / Symfony 8.0 / PostgreSQL / RabbitMQ)
- Conventions: routing, error handling, service config, testing, CS Fixer rules

### Step 2 — Domain language (read once, always)

Read `CONTEXT.md` in full. It defines all bounded-context terms, relationships, and flagged ambiguities. Never invent terminology — use what is defined there.

### Step 3 — Architecture map (embedded here, no exploration needed)

```
src/
└── {Context}/
    ├── Domain/
    │   ├── Model/              # Entities
    │   ├── Port/               # Repository & service interfaces
    │   ├── ValueObject/        # Value objects
    │   └── Exception/          # Domain exceptions
    ├── Application/
    │   ├── UseCase/{Name}/     # Command + Handler  OR  Query + Handler (one folder per use case)
    │   └── Service/            # Application-layer service interfaces
    ├── Infrastructure/
    │   ├── Persistence/Doctrine/  # Repository implementations
    │   └── Service/               # Infrastructure service implementations
    └── UI/Http/Controller/{Name}/ # Controller + Request DTO (one folder per use case)

config/services/{context}.yaml      # DI wiring for this context (must have _instanceof)
config/services/exceptions.yaml     # Exception → HTTP status mappings (shared, never in context YAMLs)
tests/{Context}/                    # Mirrors src/{Context}/ structure
```

**Bounded contexts:** `Hotel`, `Room`, `Booker`, `Availability` — each fully isolated under `src/`.

**Use case pattern:** Each use case is a folder containing exactly two files — a Message class (Command or Query) and its Handler.

**Config rule:** Every new handler must be reachable via `_instanceof` in the context YAML. If DI wiring details are needed while writing the plan, invoke the `symfony-service-config` skill.

### Step 4 — GitNexus for dynamic lookup (only if needed)

If the target context or its existing symbols are unclear, use:
- `gitnexus://repo/book.it/clusters` — all functional areas
- `gitnexus_query({query: "concept"})` — find related symbols and flows

Do NOT use `find`, directory listings, or broad file reads to explore structure.

### Step 5 — Canonical file references (max 3 files, only if a pattern is ambiguous)

Read only what you need to resolve a specific ambiguity:

| Pattern | Canonical file(s) |
|---------|-------------------|
| Command use case | `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommand.php` + `BlockPeriodCommandHandler.php` |
| Query use case | `src/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQuery.php` + `GetBlockedPeriodQueryHandler.php` |
| Controller + Request DTO | `src/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodController.php` + `BlockPeriodRequest.php` |
| Domain entity | `src/Availability/Domain/Model/BlockedPeriod.php` |
| DI config | `config/services/availability.yaml` |

**Hard limit: read at most 3 files before starting to write the plan.**

## Forbidden Actions

- **No** `find`, `ls`, or directory listing to explore structure — use the map above
- **No** reading test files for context
- **No** reading more than 3 files before writing

## Plan Format

After gathering context, write a numbered implementation plan with:

1. **New files to create** — full path, one-line purpose
2. **Existing files to modify** — full path, what changes and why
3. **DI config** — what to add to which context YAML
4. **Exception mapping** — if new domain exceptions are introduced
5. **Tests** — unit / integration / functional per CLAUDE.md testing rules
6. **OpenAPI** — reminder to run `make openapi` if routes are added or changed

Keep steps atomic and independently verifiable. Each step should be completable and testable on its own.
