---
name: symfony-testing
description: "Use when writing or debugging tests in this project — choosing the right test group, setting up integration tests, or writing functional test helpers. Examples: 'Write a test for this handler', 'Add a functional test', 'Why does my KernelTestCase fail to compile?'"
---

# Symfony Testing Conventions

## Test groups

| Test class | Attribute | Run with |
|---|---|---|
| `TestCase` (no Symfony kernel) | `#[Group('unit')]` | `make unit-test-quiet` |
| `KernelTestCase` (needs kernel) | `#[Group('integration')]` | `make unit-test-quiet` |
| Controller tests | `#[Group('functional')]` | `make functional-test` |

Tests without a `#[Group]` attribute are silently skipped by `unit-test-quiet`.

## Integration tests require a compilable DI container

`KernelTestCase` builds the full Symfony container on `setUp`. If any interface registered in a context YAML has no concrete implementation, the container fails to compile and the test hangs or errors immediately.

**Always create the Doctrine repository (or any other concrete implementation) before or in the same task as the integration tests that exercise the handler.**

## Functional test helpers must accept `KernelBrowser` as a parameter

`static::getClient()` returns `AbstractBrowser|null` — PHPStan rejects calls on it. Pass `$client` explicitly:

```php
// correct
private function registerHotelAndGetId(KernelBrowser $client): string { ... }

// wrong — static::getClient() can be null
private function registerHotelAndGetId(): string {
    $client = static::getClient(); // PHPStan error
    ...
}
```

## Database isolation

PHPUnit is configured with `DAMA\DoctrineTestBundle` — each test wraps DB operations in a transaction that is rolled back, so the database is reset between tests without truncation.
