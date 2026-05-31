# Events Catalog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate `domainevents.yaml` at the project root — a single YAML file cataloguing all domain events and their listeners, produced by the Symfony console command `app:events:catalog`.

**Architecture:** A Symfony console command in `Shared/UI/Console/` reads all registered listeners from `EventDispatcherInterface`, filters to `App\Shared\Domain\Event\` events, extracts property names and types via PHP reflection, then writes `domainevents.yaml` using `Symfony\Component\Yaml\Yaml::dump()`. No new attributes or annotations on event classes.

**Tech Stack:** PHP 8.4, Symfony 8.0 Console, Symfony EventDispatcher, symfony/yaml, PHPUnit 11 (KernelTestCase), Docker (`$(DOCKER_COMPOSE_RUN)`).

---

## Files

| Action | Path | Purpose |
|--------|------|---------|
| Create | `src/Shared/UI/Console/GenerateEventsCatalogCommand.php` | Console command — reads listeners + reflection, writes YAML |
| Create | `tests/Shared/UI/Console/GenerateEventsCatalogCommandTest.php` | Functional test — boots kernel, runs command, asserts catalog |
| Modify | `config/services/shared.yaml` | Bind `%kernel.project_dir%` to `$projectDir` for the command |
| Modify | `Makefile` | Add `make events` target |

---

### Task 1: Write the failing functional test

**Files:**
- Create: `tests/Shared/UI/Console/GenerateEventsCatalogCommandTest.php`

- [ ] **Step 1.1: Create the test file**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI\Console;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

#[Group('functional')]
final class GenerateEventsCatalogCommandTest extends KernelTestCase
{
    public function testGeneratesDomainEventsYaml(): void
    {
        $kernel = self::bootKernel();
        $outputFile = $kernel->getProjectDir().'/domainevents.yaml';

        $application = new Application($kernel);
        $command = $application->find('app:events:catalog');
        $tester = new CommandTester($command);

        $tester->execute([]);

        try {
            self::assertSame(0, $tester->getStatusCode());
            self::assertFileExists($outputFile);

            $catalog = Yaml::parseFile($outputFile);
            self::assertArrayHasKey('generated_at', $catalog);

            $events = $catalog['events'];

            foreach ([
                'ReservationCreated',
                'ReservationConfirmed',
                'ReservationExpired',
                'ReservationCheckedOut',
                'ReservationPaymentCancelled',
            ] as $eventName) {
                self::assertArrayHasKey($eventName, $events, "Event $eventName missing from catalog");
                self::assertNotEmpty($events[$eventName]['listeners'], "Event $eventName has no listeners");
                foreach ($events[$eventName]['listeners'] as $listener) {
                    self::assertArrayHasKey('context', $listener);
                    self::assertArrayHasKey('class', $listener);
                }
            }
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }
}
```

- [ ] **Step 1.2: Run the test to confirm it fails**

```bash
docker compose run --no-deps php bin/phpunit --group=functional tests/Shared/UI/Console/GenerateEventsCatalogCommandTest.php
```

Expected: **FAIL** — `Command "app:events:catalog" is not defined.`

---

### Task 2: Create the console command

**Files:**
- Create: `src/Shared/UI/Console/GenerateEventsCatalogCommand.php`

- [ ] **Step 2.1: Create the command class**

```php
<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'app:events:catalog', description: 'Generate domainevents.yaml from registered domain event listeners')]
final class GenerateEventsCatalogCommand extends Command
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $catalog = [];

        foreach ($this->eventDispatcher->getListeners() as $eventClass => $listeners) {
            if (!str_starts_with($eventClass, 'App\\Shared\\Domain\\Event\\')) {
                continue;
            }

            $reflection = new \ReflectionClass($eventClass);
            $properties = [];
            $constructor = $reflection->getConstructor();

            if ($constructor !== null) {
                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();
                    $properties[$param->getName()] = $type !== null ? (string) $type : 'mixed';
                }
            }

            $listenerEntries = [];
            foreach ($listeners as $listener) {
                if (!is_array($listener) || !is_object($listener[0])) {
                    continue;
                }
                $listenerClass = $listener[0]::class;
                if (!str_starts_with($listenerClass, 'App\\')) {
                    continue;
                }
                $parts = explode('\\', $listenerClass);
                $listenerEntries[] = [
                    'context' => $parts[1] ?? 'Unknown',
                    'class' => $listenerClass,
                ];
            }

            $catalog[$reflection->getShortName()] = [
                'class' => $eventClass,
                'properties' => $properties,
                'listeners' => $listenerEntries,
            ];
        }

        $yaml = Yaml::dump([
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'events' => $catalog,
        ], 4, 2);

        file_put_contents($this->projectDir.'/domainevents.yaml', $yaml);

        $output->writeln(sprintf('<info>Generated domainevents.yaml with %d events.</info>', count($catalog)));

        return Command::SUCCESS;
    }
}
```

---

### Task 3: Wire DI config and add Makefile target

**Files:**
- Modify: `config/services/shared.yaml`
- Modify: `Makefile`

`App\Shared\` is already auto-scanned, but `$projectDir` (a `string` parameter) must be explicitly bound — autowiring only works for typed services, not scalar parameters.

- [ ] **Step 3.1: Add the `$projectDir` argument to `config/services/shared.yaml`**

Add after the existing `App\Shared\Infrastructure\Http\ApiDeprecationResponseListener:` block:

```yaml
    App\Shared\UI\Console\GenerateEventsCatalogCommand:
        arguments:
            $projectDir: '%kernel.project_dir%'
```

The full file ends as:

```yaml
parameters:
    app.api.current_version: 'v1'
    app.api.deprecated_versions: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true
    App\Shared\:
        resource: '../../src/Shared/'
        exclude:
            - '../../src/Shared/**/*Exception.php'
            - '../../src/Shared/Infrastructure/Doctrine/SearchPathMiddleware.php'

    App\Shared\Infrastructure\Transaction\DoctrineTransactionManager:
        arguments:
            $bookit: '@doctrine.dbal.bookit_connection'

    App\Shared\Infrastructure\Http\ApiVersionRedirectListener:
        arguments:
            $currentApiVersion: '%app.api.current_version%'
            $deprecatedVersions: '%app.api.deprecated_versions%'

    App\Shared\Infrastructure\Http\ApiDeprecationResponseListener:
        arguments:
            $currentApiVersion: '%app.api.current_version%'
            $deprecatedVersions: '%app.api.deprecated_versions%'

    App\Shared\UI\Console\GenerateEventsCatalogCommand:
        arguments:
            $projectDir: '%kernel.project_dir%'
```

- [ ] **Step 3.2: Add the `make events` target to `Makefile`**

Locate the `openapi:` target and add `events:` immediately after it:

```makefile
events: ## Generate domainevents.yaml from registered domain event listeners
	$(DOCKER_COMPOSE_RUN) --no-deps php bin/console app:events:catalog
```

(Use a tab character for indentation, not spaces.)

---

### Task 4: Run tests, lint, and commit

- [ ] **Step 4.1: Run the functional test — expect PASS**

```bash
docker compose run --no-deps php bin/phpunit --group=functional tests/Shared/UI/Console/GenerateEventsCatalogCommandTest.php
```

Expected: **1 test, 1 passed**

- [ ] **Step 4.2: Run the full test suite**

```bash
make test
```

Expected: all tests pass, no regressions.

- [ ] **Step 4.3: Run lint**

```bash
make lint
```

Expected: CS Fixer, PHPStan, and Deptrac all pass.

- [ ] **Step 4.4: Generate the catalog and commit it**

```bash
make events
```

Verify `domainevents.yaml` exists at the project root with the 5 events.

- [ ] **Step 4.5: Check affected scope before committing**

```bash
# In your shell (not Docker):
npx gitnexus detect-changes
```

Expected: only `GenerateEventsCatalogCommand`, `GenerateEventsCatalogCommandTest`, `shared.yaml`, `Makefile`, `domainevents.yaml`.

- [ ] **Step 4.6: Commit**

```bash
git add src/Shared/UI/Console/GenerateEventsCatalogCommand.php \
        tests/Shared/UI/Console/GenerateEventsCatalogCommandTest.php \
        config/services/shared.yaml \
        Makefile \
        domainevents.yaml
git commit -m "feat(shared): add app:events:catalog command and generate domainevents.yaml"
```
