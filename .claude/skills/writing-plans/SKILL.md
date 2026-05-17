---
name: writing-plans
description: "Use when writing an implementation plan for this project. Defines a minimal context-gathering protocol to avoid broad codebase exploration."
---

# Writing Implementation Plans — book.it

This skill **extends** `superpowers:writing-plans`. It defines how to gather context for this project, then hands off to the superpowers skill for plan structure, task granularity, self-review, and execution handoff.

## Step 1 — Gather context (this skill)

Follow this protocol in order, stopping when you have enough.

### 1.1 — Already in context (read nothing)

CLAUDE.md is always loaded. It contains:
- Stack (PHP 8.4 / Symfony 8.0 / PostgreSQL / RabbitMQ)
- Conventions: routing, error handling, service config, testing, CS Fixer rules

### 1.2 — Domain language (read once, always)

Read `CONTEXT.md` in full. It defines all bounded-context terms, relationships, and flagged ambiguities. Never invent terminology — use what is defined there.

### 1.3 — Architecture map (embedded here, no exploration needed)

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
    └── UI/Http/
        └── Controller/{Name}/  # Controller + Request DTO (one folder per use case) + One serializer per aggregate (if controllers return structured JSON)

config/services/{context}.yaml      # DI wiring for this context (must have _instanceof)
config/services/exceptions.yaml     # Exception → HTTP status mappings (shared, never in context YAMLs)
tests/{Context}/                    # Mirrors src/{Context}/ structure
```

**Use case pattern:** Each use case is a folder containing exactly two files — a Message class (Command or Query) and its Handler.

**Config rule:** Every new handler must be reachable via `_instanceof` in the context YAML. If DI wiring details are needed while writing the plan, invoke the `symfony-service-config` skill.

### 1.4 — GitNexus for dynamic lookup (only if needed)

If the target context or its existing symbols are unclear, use:
- `gitnexus://repo/book.it/clusters` — all functional areas
- `gitnexus_query({query: "concept"})` — find related symbols and flows

Do NOT use `find`, directory listings, or broad file reads to explore structure.

### 1.5 — Canonical file references (max 3 files, only if a pattern is ambiguous)

Read only what you need to resolve a specific ambiguity:

| Pattern                  | Canonical file(s)                                                                                                                      |
|--------------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| Command use case         | `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommand.php` + `BlockPeriodCommandHandler.php`                            |
| Query use case           | `src/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQuery.php` + `GetBlockedPeriodQueryHandler.php`                 |
| Controller + Request DTO | `src/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodController.php` + `BlockPeriodRequest.php` + `BlockedPeriodSerializer.php` |
| Domain entity            | `src/Availability/Domain/Model/BlockedPeriod.php`                                                                                      |
| DI config                | `config/services/availability.yaml`                                                                                                    |

**Hard limit: read at most 3 files before moving on.**

## Forbidden actions during context gathering

- **No** `find`, `ls`, or directory listing to explore structure — use the map above
- **No** reading test files for context
- **No** reading more than 3 files before writing

## Step 2 — Write the plan (superpowers:writing-plans)

Once context is gathered, invoke the `superpowers:writing-plans` skill to write and structure the plan.

When writing plan tasks, ensure each task covers all relevant sections for this project:
- **New files to create** — full path, one-line purpose
- **Existing files to modify** — full path, what changes and why
- **DI config** — what to add to which context YAML (`config/services/{context}.yaml`)
- **Exception mapping** — new domain exceptions → `config/services/exceptions.yaml`
- **Serializers** *(if controllers return structured JSON shapes)* — one per aggregate in `src/{Context}/UI/Http/Controller/{Name}/`, injected into controllers
- **Tests** — unit / integration / functional per CLAUDE.md testing rules
- **OpenAPI** — run `make openapi` if routes are added or changed
