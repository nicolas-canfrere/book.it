# Context Map Generator — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate `contextmap.yaml` and `docs/context-map.md` from source code, with a validation script usable in CI.

**Architecture:** Two standalone PHP scripts (`bin/generate-contextmap.php`, `bin/check-contextmap.php`) backed by testable classes in `tools/ContextMap/`. Data sources: `src/*/Application/Contract/` filesystem scan + `deptrac-contexts.yaml` ruleset parsing. No Symfony container dependency.

**Tech Stack:** PHP 8.4, `symfony/yaml` (already in vendor), PHPUnit, Docker via `make`

---

## File Map

| Action | Path | Purpose |
|---|---|---|
| Modify | `composer.json` | Add `Tools\\` PSR-4 autoload-dev entry |
| Create | `tools/ContextMap/ContractScanner.php` | Scans `src/*/Application/Contract/*.php` |
| Create | `tools/ContextMap/DeptracRulesetParser.php` | Parses `deptrac-contexts.yaml` ruleset |
| Create | `tools/ContextMap/ContextMapBuilder.php` | Combines scan + parse → data model |
| Create | `tools/ContextMap/YamlWriter.php` | Writes `contextmap.yaml` |
| Create | `tools/ContextMap/MermaidWriter.php` | Writes `docs/context-map.md` |
| Create | `tools/ContextMap/ContextMapChecker.php` | Validates `contextmap.yaml` against `src/` |
| Create | `bin/generate-contextmap.php` | CLI entry point for generation |
| Create | `bin/check-contextmap.php` | CLI entry point for validation |
| Modify | `Makefile` | Add `contextmap` and `contextmap-check` targets |
| Create | `tests/ContextMap/ContractScannerTest.php` | Unit tests |
| Create | `tests/ContextMap/DeptracRulesetParserTest.php` | Unit tests |
| Create | `tests/ContextMap/ContextMapBuilderTest.php` | Unit tests |
| Create | `tests/ContextMap/YamlWriterTest.php` | Unit tests |
| Create | `tests/ContextMap/MermaidWriterTest.php` | Unit tests |
| Create | `tests/ContextMap/ContextMapCheckerTest.php` | Unit tests |

---

## Task 1: Add `Tools\\` autoloading to composer.json

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Edit composer.json**

Find the `autoload-dev` block (currently has `"App\\Tests\\": "tests/"`) and add `Tools\\`:

```json
"autoload-dev": {
    "psr-4": {
        "App\\Tests\\": "tests/",
        "Tools\\": "tools/"
    }
},
```

- [ ] **Step 2: Dump autoload inside Docker**

```bash
docker compose --progress quiet run --rm --remove-orphans --no-deps php composer dump-autoload
```

Expected: `Generated autoload files` (no errors)

- [ ] **Step 3: Commit**

```bash
git add composer.json vendor/composer/
git commit -m "chore: add Tools PSR-4 autoload-dev mapping for contextmap scripts"
```

---

## Task 2: ContractScanner (TDD)

**Files:**
- Create: `tools/ContextMap/ContractScanner.php`
- Create: `tests/ContextMap/ContractScannerTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/ContextMap/ContractScannerTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\ContractScanner;

#[Group('unit')]
class ContractScannerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/contextmap_' . uniqid();
        mkdir($this->tmpDir . '/Booker/Application/Contract', 0777, true);
        file_put_contents($this->tmpDir . '/Booker/Application/Contract/BookerFinderInterface.php', '<?php');
        file_put_contents($this->tmpDir . '/Booker/Application/Contract/BookerView.php', '<?php');
        file_put_contents($this->tmpDir . '/Booker/Application/Contract/AccountRegistrationFailedException.php', '<?php');
        mkdir($this->tmpDir . '/Hotel/Application/Contract', 0777, true);
        file_put_contents($this->tmpDir . '/Hotel/Application/Contract/HotelFinderInterface.php', '<?php');
        file_put_contents($this->tmpDir . '/Hotel/Application/Contract/HotelView.php', '<?php');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : unlink($file);
        }
        rmdir($dir);
    }

    public function test_scans_interfaces_per_context(): void
    {
        $result = (new ContractScanner())->scan($this->tmpDir);

        self::assertSame(
            ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
            $result['Booker']['interfaces']
        );
    }

    public function test_scans_views_per_context(): void
    {
        $result = (new ContractScanner())->scan($this->tmpDir);

        self::assertSame(
            ['App\\Booker\\Application\\Contract\\BookerView'],
            $result['Booker']['published_language']
        );
    }

    public function test_ignores_non_interface_non_view_files(): void
    {
        $result = (new ContractScanner())->scan($this->tmpDir);

        self::assertCount(1, $result['Booker']['interfaces']);
        self::assertCount(1, $result['Booker']['published_language']);
    }

    public function test_scans_multiple_contexts(): void
    {
        $result = (new ContractScanner())->scan($this->tmpDir);

        self::assertArrayHasKey('Booker', $result);
        self::assertArrayHasKey('Hotel', $result);
    }
}
```

- [ ] **Step 2: Run tests — expect FAIL (class not found)**

```bash
ARGS="--filter=ContractScannerTest" make unit-test
```

Expected: `Error: Class "Tools\ContextMap\ContractScanner" not found`

- [ ] **Step 3: Implement ContractScanner**

Create `tools/ContextMap/ContractScanner.php`:

```php
<?php
declare(strict_types=1);

namespace Tools\ContextMap;

final class ContractScanner
{
    /** @return array<string, array{interfaces: string[], published_language: string[]}> */
    public function scan(string $srcDir): array
    {
        $result = [];

        foreach (glob($srcDir . '/*/Application/Contract') ?: [] as $contractDir) {
            preg_match('#/([^/]+)/Application/Contract$#', $contractDir, $matches);
            $context = $matches[1];
            $interfaces = [];
            $views = [];

            foreach (glob($contractDir . '/*.php') ?: [] as $file) {
                $name = basename($file, '.php');
                $fqcn = 'App\\' . $context . '\\Application\\Contract\\' . $name;

                if (str_ends_with($name, 'Interface')) {
                    $interfaces[] = $fqcn;
                } elseif (str_ends_with($name, 'View')) {
                    $views[] = $fqcn;
                }
            }

            $result[$context] = ['interfaces' => $interfaces, 'published_language' => $views];
        }

        return $result;
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
ARGS="--filter=ContractScannerTest" make unit-test
```

Expected: `OK (4 tests, 4 assertions)`

- [ ] **Step 5: Commit**

```bash
git add tools/ContextMap/ContractScanner.php tests/ContextMap/ContractScannerTest.php
git commit -m "feat(contextmap): add ContractScanner"
```

---

## Task 3: DeptracRulesetParser (TDD)

**Files:**
- Create: `tools/ContextMap/DeptracRulesetParser.php`
- Create: `tests/ContextMap/DeptracRulesetParserTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/ContextMap/DeptracRulesetParserTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\DeptracRulesetParser;

#[Group('unit')]
class DeptracRulesetParserTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/deptrac_' . uniqid() . '.yaml';
        file_put_contents($this->tmpFile, <<<'YAML'
deptrac:
    ruleset:
        Reservation:
            - ReservationContract
            - AvailabilityContract
            - BookerContract
            - Shared
            - Vendor
        Hotel:
            - HotelContract
            - Shared
            - Vendor
        Notification:
            - BookerContract
            - Shared
            - Vendor
        ReservationContract: ~
        HotelContract: ~
        BookerContract: ~
        Shared:
            - Vendor
        Vendor: ~
YAML);
    }

    protected function tearDown(): void
    {
        unlink($this->tmpFile);
    }

    public function test_extracts_consumed_contexts(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertSame(['Availability', 'Booker'], $result['Reservation']);
    }

    public function test_skips_own_contract(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertNotContains('Reservation', $result['Reservation']);
    }

    public function test_context_with_no_consumed_contracts(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertSame([], $result['Hotel']);
    }

    public function test_skips_contract_layers(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertArrayNotHasKey('ReservationContract', $result);
        self::assertArrayNotHasKey('HotelContract', $result);
    }

    public function test_context_without_own_contract_layer(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile);

        self::assertArrayHasKey('Notification', $result);
        self::assertSame(['Booker'], $result['Notification']);
    }
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
ARGS="--filter=DeptracRulesetParserTest" make unit-test
```

Expected: `Error: Class "Tools\ContextMap\DeptracRulesetParser" not found`

- [ ] **Step 3: Implement DeptracRulesetParser**

Create `tools/ContextMap/DeptracRulesetParser.php`:

```php
<?php
declare(strict_types=1);

namespace Tools\ContextMap;

use Symfony\Component\Yaml\Yaml;

final class DeptracRulesetParser
{
    /** @return array<string, string[]> context → list of consumed context names */
    public function parse(string $deptracYamlPath): array
    {
        $data = Yaml::parseFile($deptracYamlPath);
        $ruleset = $data['deptrac']['ruleset'] ?? [];
        $result = [];

        foreach ($ruleset as $context => $dependencies) {
            if (null === $dependencies || str_ends_with($context, 'Contract')) {
                continue;
            }

            $consumed = [];
            foreach ($dependencies as $dep) {
                if (str_ends_with($dep, 'Contract') && $dep !== $context . 'Contract') {
                    $consumed[] = substr($dep, 0, -strlen('Contract'));
                }
            }

            $result[$context] = $consumed;
        }

        return $result;
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
ARGS="--filter=DeptracRulesetParserTest" make unit-test
```

Expected: `OK (5 tests, 5 assertions)`

- [ ] **Step 5: Commit**

```bash
git add tools/ContextMap/DeptracRulesetParser.php tests/ContextMap/DeptracRulesetParserTest.php
git commit -m "feat(contextmap): add DeptracRulesetParser"
```

---

## Task 4: ContextMapBuilder (TDD)

**Files:**
- Create: `tools/ContextMap/ContextMapBuilder.php`
- Create: `tests/ContextMap/ContextMapBuilderTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/ContextMap/ContextMapBuilderTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\ContextMapBuilder;

#[Group('unit')]
class ContextMapBuilderTest extends TestCase
{
    private array $contracts = [
        'Booker' => [
            'interfaces' => ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
            'published_language' => ['App\\Booker\\Application\\Contract\\BookerView'],
        ],
        'Hotel' => [
            'interfaces' => ['App\\Hotel\\Application\\Contract\\HotelFinderInterface'],
            'published_language' => ['App\\Hotel\\Application\\Contract\\HotelView'],
        ],
    ];

    private array $consumes = [
        'Room' => ['Hotel'],
        'Reservation' => ['Booker'],
    ];

    public function test_includes_all_contexts(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertArrayHasKey('Booker', $result['contexts']);
        self::assertArrayHasKey('Hotel', $result['contexts']);
        self::assertArrayHasKey('Room', $result['contexts']);
        self::assertArrayHasKey('Reservation', $result['contexts']);
    }

    public function test_sets_open_host_services(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertSame(
            ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
            $result['contexts']['Booker']['open_host_services']['interfaces']
        );
    }

    public function test_builds_consumed_by_from_consumes(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertContains('Reservation', $result['contexts']['Booker']['consumed_by']);
        self::assertContains('Room', $result['contexts']['Hotel']['consumed_by']);
    }

    public function test_builds_consumes_entries(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertSame([['context' => 'Hotel']], $result['contexts']['Room']['consumes']);
    }

    public function test_context_without_contracts_has_empty_open_host_services(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertSame([], $result['contexts']['Room']['open_host_services']['interfaces']);
        self::assertSame([], $result['contexts']['Room']['open_host_services']['published_language']);
    }

    public function test_sets_version_and_generated_at(): void
    {
        $result = (new ContextMapBuilder())->build($this->contracts, $this->consumes);

        self::assertSame('1.0', $result['version']);
        self::assertArrayHasKey('generated_at', $result);
    }
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
ARGS="--filter=ContextMapBuilderTest" make unit-test
```

Expected: `Error: Class "Tools\ContextMap\ContextMapBuilder" not found`

- [ ] **Step 3: Implement ContextMapBuilder**

Create `tools/ContextMap/ContextMapBuilder.php`:

```php
<?php
declare(strict_types=1);

namespace Tools\ContextMap;

final class ContextMapBuilder
{
    /**
     * @param array<string, array{interfaces: string[], published_language: string[]}> $contracts
     * @param array<string, string[]> $consumes
     */
    public function build(array $contracts, array $consumes): array
    {
        $consumedBy = [];
        foreach ($consumes as $consumer => $producers) {
            foreach ($producers as $producer) {
                $consumedBy[$producer][] = $consumer;
            }
        }

        $allProducers = [];
        foreach ($consumes as $deps) {
            foreach ($deps as $dep) {
                $allProducers[] = $dep;
            }
        }

        $allContexts = array_unique(array_merge(
            array_keys($contracts),
            array_keys($consumes),
            $allProducers
        ));
        sort($allContexts);

        $contexts = [];
        foreach ($allContexts as $context) {
            $contexts[$context] = [
                'open_host_services' => [
                    'interfaces' => $contracts[$context]['interfaces'] ?? [],
                    'published_language' => $contracts[$context]['published_language'] ?? [],
                ],
                'consumed_by' => $consumedBy[$context] ?? [],
                'consumes' => array_map(
                    static fn(string $p): array => ['context' => $p],
                    $consumes[$context] ?? []
                ),
            ];
        }

        return [
            'version' => '1.0',
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'contexts' => $contexts,
        ];
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
ARGS="--filter=ContextMapBuilderTest" make unit-test
```

Expected: `OK (6 tests, 8 assertions)`

- [ ] **Step 5: Commit**

```bash
git add tools/ContextMap/ContextMapBuilder.php tests/ContextMap/ContextMapBuilderTest.php
git commit -m "feat(contextmap): add ContextMapBuilder"
```

---

## Task 5: YamlWriter + MermaidWriter (TDD)

**Files:**
- Create: `tools/ContextMap/YamlWriter.php`
- Create: `tools/ContextMap/MermaidWriter.php`
- Create: `tests/ContextMap/YamlWriterTest.php`
- Create: `tests/ContextMap/MermaidWriterTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/ContextMap/YamlWriterTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\YamlWriter;

#[Group('unit')]
class YamlWriterTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/contextmap_' . uniqid() . '.yaml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function test_writes_yaml_file(): void
    {
        (new YamlWriter())->write(['version' => '1.0', 'contexts' => []], $this->tmpFile);

        self::assertFileExists($this->tmpFile);
    }

    public function test_file_starts_with_generated_comment(): void
    {
        (new YamlWriter())->write(['version' => '1.0', 'contexts' => []], $this->tmpFile);

        self::assertStringStartsWith('# Generated', file_get_contents($this->tmpFile));
    }

    public function test_contains_version(): void
    {
        (new YamlWriter())->write(['version' => '1.0', 'contexts' => []], $this->tmpFile);

        self::assertStringContainsString("version: '1.0'", file_get_contents($this->tmpFile));
    }
}
```

Create `tests/ContextMap/MermaidWriterTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tools\ContextMap\MermaidWriter;

#[Group('unit')]
class MermaidWriterTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/context-map_' . uniqid() . '.md';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function contextMap(): array
    {
        return [
            'version' => '1.0',
            'contexts' => [
                'Booker' => [
                    'open_host_services' => [
                        'interfaces' => ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
                        'published_language' => ['App\\Booker\\Application\\Contract\\BookerView'],
                    ],
                    'consumed_by' => ['Reservation'],
                    'consumes' => [],
                ],
                'Reservation' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => [],
                    'consumes' => [['context' => 'Booker']],
                ],
            ],
        ];
    }

    public function test_writes_markdown_file(): void
    {
        (new MermaidWriter())->write($this->contextMap(), $this->tmpFile);

        self::assertFileExists($this->tmpFile);
    }

    public function test_contains_mermaid_block(): void
    {
        (new MermaidWriter())->write($this->contextMap(), $this->tmpFile);
        $content = file_get_contents($this->tmpFile);

        self::assertStringContainsString('```mermaid', $content);
        self::assertStringContainsString('graph LR', $content);
    }

    public function test_contains_edge_with_interface_label(): void
    {
        (new MermaidWriter())->write($this->contextMap(), $this->tmpFile);

        self::assertStringContainsString(
            'Reservation -->|BookerFinderInterface| Booker',
            file_get_contents($this->tmpFile)
        );
    }

    public function test_contains_generated_comment(): void
    {
        (new MermaidWriter())->write($this->contextMap(), $this->tmpFile);

        self::assertStringContainsString('Generated', file_get_contents($this->tmpFile));
    }
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
ARGS="--filter=YamlWriterTest|MermaidWriterTest" make unit-test
```

Expected: two "class not found" errors

- [ ] **Step 3: Implement YamlWriter**

Create `tools/ContextMap/YamlWriter.php`:

```php
<?php
declare(strict_types=1);

namespace Tools\ContextMap;

use Symfony\Component\Yaml\Yaml;

final class YamlWriter
{
    public function write(array $contextMap, string $outputPath): void
    {
        $header = "# Generated — do not edit manually. Run: make contextmap\n";
        file_put_contents($outputPath, $header . Yaml::dump($contextMap, 6, 2));
    }
}
```

- [ ] **Step 4: Implement MermaidWriter**

Create `tools/ContextMap/MermaidWriter.php`:

```php
<?php
declare(strict_types=1);

namespace Tools\ContextMap;

final class MermaidWriter
{
    public function write(array $contextMap, string $outputPath): void
    {
        $lines = [
            '# Context Map',
            '',
            '> Generated — do not edit manually. Run: `make contextmap`',
            '',
            '```mermaid',
            'graph LR',
        ];

        foreach ($contextMap['contexts'] as $consumer => $data) {
            foreach ($data['consumes'] as $relation) {
                $producer = $relation['context'];
                $interfaces = $contextMap['contexts'][$producer]['open_host_services']['interfaces'] ?? [];
                $label = [] !== $interfaces ? $this->shortName($interfaces[0]) : $producer;
                $lines[] = "  {$consumer} -->|{$label}| {$producer}";
            }
        }

        $lines[] = '```';
        $lines[] = '';

        file_put_contents($outputPath, implode("\n", $lines));
    }

    private function shortName(string $fqcn): string
    {
        return substr($fqcn, strrpos($fqcn, '\\') + 1);
    }
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
ARGS="--filter=YamlWriterTest|MermaidWriterTest" make unit-test
```

Expected: `OK (7 tests, 7 assertions)`

- [ ] **Step 6: Commit**

```bash
git add tools/ContextMap/YamlWriter.php tools/ContextMap/MermaidWriter.php \
        tests/ContextMap/YamlWriterTest.php tests/ContextMap/MermaidWriterTest.php
git commit -m "feat(contextmap): add YamlWriter and MermaidWriter"
```

---

## Task 6: generate-contextmap.php + Makefile target

**Files:**
- Create: `bin/generate-contextmap.php`
- Modify: `Makefile`

- [ ] **Step 1: Create the generation script**

Create `bin/generate-contextmap.php`:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Tools\ContextMap\ContractScanner;
use Tools\ContextMap\ContextMapBuilder;
use Tools\ContextMap\DeptracRulesetParser;
use Tools\ContextMap\MermaidWriter;
use Tools\ContextMap\YamlWriter;

$root     = dirname(__DIR__);
$contracts = (new ContractScanner())->scan($root . '/src');
$consumes  = (new DeptracRulesetParser())->parse($root . '/deptrac-contexts.yaml');
$map       = (new ContextMapBuilder())->build($contracts, $consumes);

(new YamlWriter())->write($map, $root . '/contextmap.yaml');
(new MermaidWriter())->write($map, $root . '/docs/context-map.md');

echo "Generated contextmap.yaml and docs/context-map.md\n";
```

- [ ] **Step 2: Add Makefile targets**

In `Makefile`, inside the `##@ OpenApi doc` section, after the `events:` target, add:

```makefile
contextmap: ## Generate contextmap.yaml and docs/context-map.md from source
	$(DOCKER_COMPOSE_RUN) --no-deps php php bin/generate-contextmap.php

contextmap-check: ## Validate contextmap.yaml against source code
	$(DOCKER_COMPOSE_RUN) --no-deps php php bin/check-contextmap.php
```

Note: the indentation must be a **tab**, not spaces.

- [ ] **Step 3: Run against the real codebase**

```bash
make contextmap
```

Expected output:
```
Generated contextmap.yaml and docs/context-map.md
```

- [ ] **Step 4: Verify contextmap.yaml is well-formed**

```bash
cat contextmap.yaml | head -40
```

Expected: starts with `# Generated`, has `version: '1.0'`, lists known contexts (Availability, Booker, Hotel, Notification, Operator, Pricing, Reservation, Room, Security).

- [ ] **Step 5: Verify docs/context-map.md**

```bash
cat docs/context-map.md
```

Expected: contains `graph LR` and edges like `Reservation -->|AvailabilityCheckerInterface| Availability`.

- [ ] **Step 6: Commit generated files + scripts**

```bash
git add bin/generate-contextmap.php Makefile contextmap.yaml docs/context-map.md
git commit -m "feat(contextmap): add generate-contextmap.php, make target, and generated output"
```

---

## Task 7: ContextMapChecker (TDD)

**Files:**
- Create: `tools/ContextMap/ContextMapChecker.php`
- Create: `tests/ContextMap/ContextMapCheckerTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/ContextMap/ContextMapCheckerTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Tools\ContextMap\ContextMapChecker;

#[Group('unit')]
class ContextMapCheckerTest extends TestCase
{
    private string $tmpDir;
    private string $tmpYaml;

    protected function setUp(): void
    {
        $this->tmpDir  = sys_get_temp_dir() . '/checker_' . uniqid();
        $this->tmpYaml = $this->tmpDir . '/contextmap.yaml';

        mkdir($this->tmpDir . '/src/Booker/Application/Contract', 0777, true);
        mkdir($this->tmpDir . '/src/Booker/Infrastructure/Service', 0777, true);
        file_put_contents(
            $this->tmpDir . '/src/Booker/Application/Contract/BookerFinderInterface.php',
            '<?php'
        );
        file_put_contents(
            $this->tmpDir . '/src/Booker/Application/Contract/BookerView.php',
            '<?php'
        );
        mkdir($this->tmpDir . '/src/Reservation/Infrastructure/Service', 0777, true);
        file_put_contents(
            $this->tmpDir . '/src/Reservation/Infrastructure/Service/BookerContactFetcher.php',
            '<?php use App\Booker\Application\Contract\BookerFinderInterface;'
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : unlink($file);
        }
        rmdir($dir);
    }

    private function writeContextMap(array $data): void
    {
        file_put_contents($this->tmpYaml, Yaml::dump($data));
    }

    public function test_ok_when_interface_class_exists(): void
    {
        $this->writeContextMap([
            'version' => '1.0',
            'contexts' => [
                'Booker' => [
                    'open_host_services' => [
                        'interfaces' => ['App\\Booker\\Application\\Contract\\BookerFinderInterface'],
                        'published_language' => [],
                    ],
                    'consumed_by' => [],
                    'consumes' => [],
                ],
            ],
        ]);

        $result = (new ContextMapChecker())->check($this->tmpYaml, $this->tmpDir . '/src');

        self::assertCount(1, $result['ok']);
        self::assertCount(0, $result['fail']);
        self::assertStringContainsString('BookerFinderInterface', $result['ok'][0]);
    }

    public function test_fail_when_interface_class_missing(): void
    {
        $this->writeContextMap([
            'version' => '1.0',
            'contexts' => [
                'Booker' => [
                    'open_host_services' => [
                        'interfaces' => ['App\\Booker\\Application\\Contract\\MissingInterface'],
                        'published_language' => [],
                    ],
                    'consumed_by' => [],
                    'consumes' => [],
                ],
            ],
        ]);

        $result = (new ContextMapChecker())->check($this->tmpYaml, $this->tmpDir . '/src');

        self::assertCount(1, $result['fail']);
        self::assertStringContainsString('MissingInterface', $result['fail'][0]);
    }

    public function test_ok_when_adapter_found(): void
    {
        $this->writeContextMap([
            'version' => '1.0',
            'contexts' => [
                'Booker' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => ['Reservation'],
                    'consumes' => [],
                ],
                'Reservation' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => [],
                    'consumes' => [['context' => 'Booker']],
                ],
            ],
        ]);

        $result = (new ContextMapChecker())->check($this->tmpYaml, $this->tmpDir . '/src');

        $adapterOk = array_filter($result['ok'], static fn($m) => str_contains($m, 'consumes'));
        self::assertNotEmpty($adapterOk);
    }

    public function test_fail_when_no_adapter_found(): void
    {
        mkdir($this->tmpDir . '/src/Room/Infrastructure', 0777, true);
        $this->writeContextMap([
            'version' => '1.0',
            'contexts' => [
                'Hotel' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => ['Room'],
                    'consumes' => [],
                ],
                'Room' => [
                    'open_host_services' => ['interfaces' => [], 'published_language' => []],
                    'consumed_by' => [],
                    'consumes' => [['context' => 'Hotel']],
                ],
            ],
        ]);

        $result = (new ContextMapChecker())->check($this->tmpYaml, $this->tmpDir . '/src');

        $adapterFail = array_filter($result['fail'], static fn($m) => str_contains($m, 'consumes'));
        self::assertNotEmpty($adapterFail);
    }
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
ARGS="--filter=ContextMapCheckerTest" make unit-test
```

Expected: `Error: Class "Tools\ContextMap\ContextMapChecker" not found`

- [ ] **Step 3: Implement ContextMapChecker**

Create `tools/ContextMap/ContextMapChecker.php`:

```php
<?php
declare(strict_types=1);

namespace Tools\ContextMap;

use Symfony\Component\Yaml\Yaml;

final class ContextMapChecker
{
    /** @return array{ok: string[], fail: string[]} */
    public function check(string $contextMapPath, string $srcDir): array
    {
        $map  = Yaml::parseFile($contextMapPath);
        $ok   = [];
        $fail = [];

        foreach ($map['contexts'] as $context => $data) {
            foreach ($data['open_host_services']['interfaces'] as $fqcn) {
                $this->checkClass($srcDir, $context, $fqcn, $ok, $fail);
            }
            foreach ($data['open_host_services']['published_language'] as $fqcn) {
                $this->checkClass($srcDir, $context, $fqcn, $ok, $fail);
            }
            foreach ($data['consumes'] as $relation) {
                $producer = $relation['context'];
                if ($this->hasAdapterFor($srcDir, $context, $producer)) {
                    $ok[] = "{$context} consumes {$producer} — adapter found";
                } else {
                    $fail[] = "{$context} consumes {$producer} — no adapter found in {$context}/Infrastructure/";
                }
            }
        }

        return ['ok' => $ok, 'fail' => $fail];
    }

    private function checkClass(string $srcDir, string $context, string $fqcn, array &$ok, array &$fail): void
    {
        $file = $srcDir . '/' . str_replace(['App\\', '\\'], ['', '/'], $fqcn) . '.php';
        $name = substr($fqcn, strrpos($fqcn, '\\') + 1);
        if (file_exists($file)) {
            $ok[] = "{$context}.{$name} — class exists";
        } else {
            $fail[] = "{$context}.{$name} — {$file} not found";
        }
    }

    private function hasAdapterFor(string $srcDir, string $consumer, string $producer): bool
    {
        $infraDir = $srcDir . '/' . $consumer . '/Infrastructure';
        if (!is_dir($infraDir)) {
            return false;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($infraDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (str_contains((string) file_get_contents($file->getPathname()), 'App\\' . $producer . '\\Application\\Contract\\')) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
ARGS="--filter=ContextMapCheckerTest" make unit-test
```

Expected: `OK (4 tests, 5 assertions)`

- [ ] **Step 5: Commit**

```bash
git add tools/ContextMap/ContextMapChecker.php tests/ContextMap/ContextMapCheckerTest.php
git commit -m "feat(contextmap): add ContextMapChecker"
```

---

## Task 8: check-contextmap.php + Makefile target + full smoke test

**Files:**
- Create: `bin/check-contextmap.php`
- `Makefile` already has the `contextmap-check` target (added in Task 6)

- [ ] **Step 1: Create the validation script**

Create `bin/check-contextmap.php`:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Tools\ContextMap\ContextMapChecker;

$root   = dirname(__DIR__);
$result = (new ContextMapChecker())->check($root . '/contextmap.yaml', $root . '/src');

foreach ($result['ok'] as $msg) {
    echo "[OK]   {$msg}\n";
}
foreach ($result['fail'] as $msg) {
    echo "[FAIL] {$msg}\n";
}

if ([] !== $result['fail']) {
    exit(1);
}
```

- [ ] **Step 2: Run the full unit test suite**

```bash
make unit-test
```

Expected: all tests pass (no failures)

- [ ] **Step 3: Run contextmap-check against the real codebase**

```bash
make contextmap-check
```

Expected: only `[OK]` lines, exit 0. If any `[FAIL]` appear, it means either a class file is missing from `src/` or an Infrastructure adapter doesn't reference the contract namespace — investigate and fix.

- [ ] **Step 4: Commit**

```bash
git add bin/check-contextmap.php
git commit -m "feat(contextmap): add check-contextmap.php and complete the contextmap toolchain"
```

---

## Done

After Task 8, the feature is complete:

- `make contextmap` → regenerates `contextmap.yaml` + `docs/context-map.md`
- `make contextmap-check` → validates the generated file against source (CI-safe, non-zero exit on failure)
- All tool classes are unit-tested
- Both generated files are versioned

Next step: open a PR for this branch (`feat/contextmap-generator`).
