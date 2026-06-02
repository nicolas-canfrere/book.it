---
paths:
  - "tests/**/*Test.php"
---

# Test Rules

## Groups

| Base class       | Required group        | Make target        |
|------------------|-----------------------|--------------------|
| `TestCase`       | `#[Group('unit')]`    | `make unit-test`   |
| `KernelTestCase` | `#[Group('integration')]` | `make unit-test` |
| `WebTestCase`    | `#[Group('functional')]`  | `make functional-test` |

Exception: `KernelTestCase` tests that insert data into a real database (DBAL connections, Doctrine repositories) must use `#[Group('functional')]`.

## Method naming

- All test methods must be named `itDoesSomething(): void` (camelCase, `it` prefix).
- The `test_snake_case` prefix is forbidden.
- Methods not prefixed with `test` require `#[Test]` attribute and `use PHPUnit\Framework\Attributes\Test;`.

```php
// correct
#[Test]
public function itReturns404WhenHotelNotFound(): void {}

// forbidden
public function test_returns_404_when_hotel_not_found(): void {}
```
