---
name: symfony-service-config
description: "Use when configuring Symfony services in this project — wiring handlers, adding resource entries, declaring public services, or registering exception mappings. Examples: 'Add a new command handler', 'Register a new context YAML', 'Map an exception to a Problem Detail'"
---

# Symfony Service Configuration

Project-specific gotchas for `config/services/*.yaml`.

## `_instanceof` block is required in every context YAML

Each context service file **must** include the `_instanceof` block to tag handlers on the Messenger buses:

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

Without it, handlers are silently unregistered → `NoHandlerForMessageException` at runtime (500, no compile-time warning).

## Never add `resource:` for directories that don't exist yet

A `resource:` path is resolved at container build time. Missing directory → `FileLocatorFileNotFoundException`, including during test runs.

```yaml
# Only add once src/Booker/UI/ exists on disk
App\Booker\UI\:
    resource: '../../src/Booker/UI/'
    exclude:
        - '../../src/Booker/UI/**/*Request.php'
```

## Temporary `public: true` for services without a production consumer

When a service has no production consumer yet, Symfony's DI compiler may inline it, making it unreachable via `getContainer()->get()` in integration tests:

```yaml
# Temporary: remove once RegisterBookerController is wired
App\Booker\Application\Service\RegisterBookerCommandFactory:
    public: true
```

## Exception mappings go in `config/services/exceptions.yaml` only

All `ExceptionProblemRegistry` `$map` entries are centralized there (imported last in `services.yaml`). Defining `ExceptionProblemRegistry` in a context YAML silently overwrites all previous definitions.

```yaml
App\Shared\Infrastructure\Http\ExceptionProblemRegistry:
    arguments:
        $map:
            App\SomeContext\Domain\Exception\SomeException:
                type: 'https://book.it/problems/some-problem'
                title: 'Some Problem'
                status: 409
```
