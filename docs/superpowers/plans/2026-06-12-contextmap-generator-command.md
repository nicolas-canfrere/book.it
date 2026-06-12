# Context Map Generator — Symfony CLI Command Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the standalone context map generator scripts into two injectable Symfony CLI commands (`app:contextmap:generate`, `app:contextmap:check`) with a new `contextmap.config.yaml` for configuration.

**Architecture:** The six tool classes from `tools/ContextMap/` (namespace `Tools\ContextMap`) are ported to `src/Shared/Infrastructure/ContextMap/` (namespace `App\Shared\Infrastructure\ContextMap`) and registered as Symfony services. Two commands in `src/Shared/UI/Console/` orchestrate them. A new `ContextMapConfigLoader` reads an optional `contextmap.config.yaml` from the project root and merges it with built-in defaults. CLI options take precedence over config.

**Tech Stack:** PHP 8.4, Symfony 8.0 Console component, `symfony/yaml`, PHPUnit 11

---

## File Map

**New files (src):**
- `src/Shared/Infrastructure/ContextMap/ContextMapConfig.php` — value object holding resolved config (absolute paths + excluded_layers)
- `src/Shared/Infrastructure/ContextMap/ContextMapConfigLoader.php` — reads `contextmap.config.yaml`, merges with defaults
- `src/Shared/Infrastructure/ContextMap/ContractScanner.php` — ported from `tools/ContextMap/ContractScanner.php`
- `src/Shared/Infrastructure/ContextMap/DeptracRulesetParser.php` — ported, `parse()` gains `array $excludedLayers` param
- `src/Shared/Infrastructure/ContextMap/ContextMapBuilder.php` — ported, namespace change only
- `src/Shared/Infrastructure/ContextMap/YamlWriter.php` — ported, namespace change only
- `src/Shared/Infrastructure/ContextMap/MermaidWriter.php` — ported, namespace change only
- `src/Shared/Infrastructure/ContextMap/ContextMapChecker.php` — ported, namespace change only
- `src/Shared/UI/Console/GenerateContextMapCommand.php` — `app:contextmap:generate`
- `src/Shared/UI/Console/CheckContextMapCommand.php` — `app:contextmap:check`

**New files (tests):**
- `tests/ContextMap/ContextMapConfigLoaderTest.php` — unit, tests merge logic
- `tests/ContextMap/GenerateContextMapCommandTest.php` — unit, tests command with mocked services
- `tests/ContextMap/CheckContextMapCommandTest.php` — unit, tests command with mocked services

**Modified files:**
- `tests/ContextMap/ContractScannerTest.php` — update `use Tools\ContextMap\ContractScanner` → `use App\Shared\Infrastructure\ContextMap\ContractScanner`
- `tests/ContextMap/DeptracRulesetParserTest.php` — same namespace update + add `itExcludesConfiguredLayers` test
- `tests/ContextMap/ContextMapBuilderTest.php` — namespace update only
- `tests/ContextMap/YamlWriterTest.php` — namespace update only
- `tests/ContextMap/MermaidWriterTest.php` — namespace update only
- `tests/ContextMap/ContextMapCheckerTest.php` — namespace update only
- `config/services/shared.yaml` — add explicit `$projectDir` wiring for two new commands
- `Makefile` — replace `bin/*.php` calls with `php bin/console` calls
- `composer.json` — remove `"Tools\\": "tools/"` from `autoload-dev`

**Deleted files:**
- `tools/ContextMap/ContractScanner.php`
- `tools/ContextMap/DeptracRulesetParser.php`
- `tools/ContextMap/ContextMapBuilder.php`
- `tools/ContextMap/YamlWriter.php`
- `tools/ContextMap/MermaidWriter.php`
- `tools/ContextMap/ContextMapChecker.php`
- `bin/generate-contextmap.php`
- `bin/check-contextmap.php`

---

## Task 1: ContextMapConfig + ContextMapConfigLoader

**Files:**
- Create: `src/Shared/Infrastructure/ContextMap/ContextMapConfig.php`
- Create: `src/Shared/Infrastructure/ContextMap/ContextMapConfigLoader.php`
- Create: `tests/ContextMap/ContextMapConfigLoaderTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/ContextMap/ContextMapConfigLoaderTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class ContextMapConfigLoaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cmconfig_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->tmpDir);
    }

    #[Test]
    public function itReturnsDefaultsWhenConfigFileAbsent(): void
    {
        $config = (new ContextMapConfigLoader())->load($this->tmpDir);

        self::assertSame($this->tmpDir . '/src/', $config->getSrcDir());
        self::assertSame($this->tmpDir . '/deptrac-contexts.yaml', $config->getDeptracFile());
        self::assertSame($this->tmpDir . '/contextmap.yaml', $config->getOutputYaml());
        self::assertSame($this->tmpDir . '/docs/context-map.md', $config->getOutputMermaid());
        self::assertContains('Shared', $config->getExcludedLayers());
        self::assertContains('Vendor', $config->getExcludedLayers());
    }

    #[Test]
    public function itMergesPartialConfig(): void
    {
        file_put_contents($this->tmpDir . '/contextmap.config.yaml', <<<'YAML'
        contextmap:
          output_yaml: custom/map.yaml
        YAML);

        $config = (new ContextMapConfigLoader())->load($this->tmpDir);

        self::assertSame($this->tmpDir . '/custom/map.yaml', $config->getOutputYaml());
        self::assertSame($this->tmpDir . '/src/', $config->getSrcDir());
    }

    #[Test]
    public function itOverridesExcludedLayers(): void
    {
        file_put_contents($this->tmpDir . '/contextmap.config.yaml', <<<'YAML'
        contextmap:
          excluded_layers:
            - MyCustomLayer
        YAML);

        $config = (new ContextMapConfigLoader())->load($this->tmpDir);

        self::assertSame(['MyCustomLayer'], $config->getExcludedLayers());
    }
}
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
make unit-test 2>&1 | grep -E "ContextMapConfigLoader|ERROR|FAIL" | head -20
```

Expected: class not found errors.

- [ ] **Step 3: Create ContextMapConfig**

Create `src/Shared/Infrastructure/ContextMap/ContextMapConfig.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

final class ContextMapConfig
{
    /** @param string[] $excludedLayers */
    public function __construct(
        private readonly string $srcDir,
        private readonly string $deptracFile,
        private readonly string $outputYaml,
        private readonly string $outputMermaid,
        private readonly array $excludedLayers,
    ) {}

    public function getSrcDir(): string { return $this->srcDir; }

    public function getDeptracFile(): string { return $this->deptracFile; }

    public function getOutputYaml(): string { return $this->outputYaml; }

    public function getOutputMermaid(): string { return $this->outputMermaid; }

    /** @return string[] */
    public function getExcludedLayers(): array { return $this->excludedLayers; }
}
```

- [ ] **Step 4: Create ContextMapConfigLoader**

Create `src/Shared/Infrastructure/ContextMap/ContextMapConfigLoader.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

use Symfony\Component\Yaml\Yaml;

final class ContextMapConfigLoader
{
    private const DEFAULTS = [
        'src_dir' => 'src/',
        'deptrac_file' => 'deptrac-contexts.yaml',
        'output_yaml' => 'contextmap.yaml',
        'output_mermaid' => 'docs/context-map.md',
        'excluded_layers' => ['Shared', 'Vendor', 'Payment', 'Search', 'Translation'],
    ];

    public function load(string $projectDir): ContextMapConfig
    {
        $data = self::DEFAULTS;
        $configFile = $projectDir . '/contextmap.config.yaml';

        if (file_exists($configFile)) {
            $parsed = Yaml::parseFile($configFile);
            $overrides = $parsed['contextmap'] ?? [];
            foreach ($overrides as $key => $value) {
                if (null !== $value && array_key_exists($key, $data)) {
                    $data[$key] = $value;
                }
            }
        }

        return new ContextMapConfig(
            srcDir: $projectDir . '/' . $data['src_dir'],
            deptracFile: $projectDir . '/' . $data['deptrac_file'],
            outputYaml: $projectDir . '/' . $data['output_yaml'],
            outputMermaid: $projectDir . '/' . $data['output_mermaid'],
            excludedLayers: $data['excluded_layers'],
        );
    }
}
```

- [ ] **Step 5: Run tests to confirm pass**

```bash
make unit-test 2>&1 | grep -E "ContextMapConfigLoader|OK|FAIL" | head -20
```

Expected: 3 tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Shared/Infrastructure/ContextMap/ContextMapConfig.php \
        src/Shared/Infrastructure/ContextMap/ContextMapConfigLoader.php \
        tests/ContextMap/ContextMapConfigLoaderTest.php
git commit -m "feat(contextmap): add ContextMapConfig and ContextMapConfigLoader"
```

---

## Task 2: Port ContractScanner

**Files:**
- Create: `src/Shared/Infrastructure/ContextMap/ContractScanner.php`
- Modify: `tests/ContextMap/ContractScannerTest.php`

- [ ] **Step 1: Update test namespace**

In `tests/ContextMap/ContractScannerTest.php`, replace:

```php
use Tools\ContextMap\ContractScanner;
```

with:

```php
use App\Shared\Infrastructure\ContextMap\ContractScanner;
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
make unit-test 2>&1 | grep -E "ContractScanner|ERROR" | head -10
```

Expected: class not found.

- [ ] **Step 3: Create ContractScanner**

Create `src/Shared/Infrastructure/ContextMap/ContractScanner.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

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

- [ ] **Step 4: Run tests to confirm pass**

```bash
make unit-test 2>&1 | grep -E "ContractScanner" | head -10
```

Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Shared/Infrastructure/ContextMap/ContractScanner.php \
        tests/ContextMap/ContractScannerTest.php
git commit -m "feat(contextmap): port ContractScanner to src/Shared/Infrastructure"
```

---

## Task 3: Port DeptracRulesetParser

**Files:**
- Create: `src/Shared/Infrastructure/ContextMap/DeptracRulesetParser.php`
- Modify: `tests/ContextMap/DeptracRulesetParserTest.php`

- [ ] **Step 1: Update test namespace and add excluded_layers test**

In `tests/ContextMap/DeptracRulesetParserTest.php`, replace:

```php
use Tools\ContextMap\DeptracRulesetParser;
```

with:

```php
use App\Shared\Infrastructure\ContextMap\DeptracRulesetParser;
```

Then add this test at the end of the class (before the closing `}`):

```php
    #[Test]
    public function itExcludesConfiguredLayers(): void
    {
        $result = (new DeptracRulesetParser())->parse($this->tmpFile, ['Shared', 'Vendor', 'Notification']);

        self::assertArrayNotHasKey('Notification', $result);
    }
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
make unit-test 2>&1 | grep -E "DeptracRulesetParser|ERROR" | head -10
```

Expected: class not found.

- [ ] **Step 3: Create DeptracRulesetParser**

Create `src/Shared/Infrastructure/ContextMap/DeptracRulesetParser.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

use Symfony\Component\Yaml\Yaml;

final class DeptracRulesetParser
{
    /**
     * @param string[] $excludedLayers
     * @return array<string, string[]>
     */
    public function parse(
        string $deptracYamlPath,
        array $excludedLayers = ['Shared', 'Vendor', 'Payment', 'Search', 'Translation'],
    ): array {
        $data = Yaml::parseFile($deptracYamlPath);
        $ruleset = $data['deptrac']['ruleset'] ?? [];
        $result = [];

        foreach ($ruleset as $context => $dependencies) {
            if (null === $dependencies || str_ends_with($context, 'Contract')) {
                continue;
            }
            if (in_array($context, $excludedLayers, true)) {
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

- [ ] **Step 4: Run tests to confirm pass**

```bash
make unit-test 2>&1 | grep -E "DeptracRulesetParser" | head -10
```

Expected: 6 tests pass (5 existing + 1 new).

- [ ] **Step 5: Commit**

```bash
git add src/Shared/Infrastructure/ContextMap/DeptracRulesetParser.php \
        tests/ContextMap/DeptracRulesetParserTest.php
git commit -m "feat(contextmap): port DeptracRulesetParser, add excluded_layers param"
```

---

## Task 4: Port ContextMapBuilder

**Files:**
- Create: `src/Shared/Infrastructure/ContextMap/ContextMapBuilder.php`
- Modify: `tests/ContextMap/ContextMapBuilderTest.php`

- [ ] **Step 1: Update test namespace**

In `tests/ContextMap/ContextMapBuilderTest.php`, replace:

```php
use Tools\ContextMap\ContextMapBuilder;
```

with:

```php
use App\Shared\Infrastructure\ContextMap\ContextMapBuilder;
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
make unit-test 2>&1 | grep -E "ContextMapBuilder|ERROR" | head -10
```

- [ ] **Step 3: Create ContextMapBuilder**

Create `src/Shared/Infrastructure/ContextMap/ContextMapBuilder.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

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

- [ ] **Step 4: Run tests to confirm pass**

```bash
make unit-test 2>&1 | grep -E "ContextMapBuilder" | head -10
```

- [ ] **Step 5: Commit**

```bash
git add src/Shared/Infrastructure/ContextMap/ContextMapBuilder.php \
        tests/ContextMap/ContextMapBuilderTest.php
git commit -m "feat(contextmap): port ContextMapBuilder to src/Shared/Infrastructure"
```

---

## Task 5: Port YamlWriter + MermaidWriter

**Files:**
- Create: `src/Shared/Infrastructure/ContextMap/YamlWriter.php`
- Create: `src/Shared/Infrastructure/ContextMap/MermaidWriter.php`
- Modify: `tests/ContextMap/YamlWriterTest.php`
- Modify: `tests/ContextMap/MermaidWriterTest.php`

- [ ] **Step 1: Update test namespaces**

In `tests/ContextMap/YamlWriterTest.php`, replace:

```php
use Tools\ContextMap\YamlWriter;
```

with:

```php
use App\Shared\Infrastructure\ContextMap\YamlWriter;
```

In `tests/ContextMap/MermaidWriterTest.php`, replace:

```php
use Tools\ContextMap\MermaidWriter;
```

with:

```php
use App\Shared\Infrastructure\ContextMap\MermaidWriter;
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
make unit-test 2>&1 | grep -E "YamlWriter|MermaidWriter|ERROR" | head -10
```

- [ ] **Step 3: Create YamlWriter**

Create `src/Shared/Infrastructure/ContextMap/YamlWriter.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

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

- [ ] **Step 4: Create MermaidWriter**

Create `src/Shared/Infrastructure/ContextMap/MermaidWriter.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

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
                if ([] === $interfaces) {
                    $label = $producer;
                } elseif (1 === count($interfaces)) {
                    $label = $this->shortName($interfaces[0]);
                } else {
                    $label = implode(', ', array_map([$this, 'shortName'], $interfaces));
                }
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

- [ ] **Step 5: Run tests to confirm pass**

```bash
make unit-test 2>&1 | grep -E "YamlWriter|MermaidWriter" | head -10
```

- [ ] **Step 6: Commit**

```bash
git add src/Shared/Infrastructure/ContextMap/YamlWriter.php \
        src/Shared/Infrastructure/ContextMap/MermaidWriter.php \
        tests/ContextMap/YamlWriterTest.php \
        tests/ContextMap/MermaidWriterTest.php
git commit -m "feat(contextmap): port YamlWriter and MermaidWriter to src/Shared/Infrastructure"
```

---

## Task 6: Port ContextMapChecker

**Files:**
- Create: `src/Shared/Infrastructure/ContextMap/ContextMapChecker.php`
- Modify: `tests/ContextMap/ContextMapCheckerTest.php`

- [ ] **Step 1: Update test namespace**

In `tests/ContextMap/ContextMapCheckerTest.php`, replace:

```php
use Tools\ContextMap\ContextMapChecker;
```

with:

```php
use App\Shared\Infrastructure\ContextMap\ContextMapChecker;
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
make unit-test 2>&1 | grep -E "ContextMapChecker|ERROR" | head -10
```

- [ ] **Step 3: Create ContextMapChecker**

Create `src/Shared/Infrastructure/ContextMap/ContextMapChecker.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

use Symfony\Component\Yaml\Yaml;

final class ContextMapChecker
{
    /** @return array{ok: string[], fail: string[]} */
    public function check(string $contextMapPath, string $srcDir): array
    {
        $map = Yaml::parseFile($contextMapPath);
        $ok = [];
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

- [ ] **Step 4: Run tests to confirm pass**

```bash
make unit-test 2>&1 | grep -E "ContextMapChecker" | head -10
```

- [ ] **Step 5: Commit**

```bash
git add src/Shared/Infrastructure/ContextMap/ContextMapChecker.php \
        tests/ContextMap/ContextMapCheckerTest.php
git commit -m "feat(contextmap): port ContextMapChecker to src/Shared/Infrastructure"
```

---

## Task 7: GenerateContextMapCommand

**Files:**
- Create: `src/Shared/UI/Console/GenerateContextMapCommand.php`
- Create: `tests/ContextMap/GenerateContextMapCommandTest.php`
- Modify: `config/services/shared.yaml`

- [ ] **Step 1: Write the failing test**

Create `tests/ContextMap/GenerateContextMapCommandTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\ContextMapBuilder;
use App\Shared\Infrastructure\ContextMap\ContextMapConfig;
use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use App\Shared\Infrastructure\ContextMap\ContractScanner;
use App\Shared\Infrastructure\ContextMap\DeptracRulesetParser;
use App\Shared\Infrastructure\ContextMap\MermaidWriter;
use App\Shared\Infrastructure\ContextMap\YamlWriter;
use App\Shared\UI\Console\GenerateContextMapCommand;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('unit')]
class GenerateContextMapCommandTest extends TestCase
{
    private string $tmpDir;
    private ContractScanner&MockObject $contractScanner;
    private DeptracRulesetParser&MockObject $deptracParser;
    private ContextMapBuilder&MockObject $builder;
    private YamlWriter&MockObject $yamlWriter;
    private MermaidWriter&MockObject $mermaidWriter;
    private ContextMapConfigLoader&MockObject $configLoader;
    private ContextMapConfig $config;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cmcmd_' . uniqid();
        mkdir($this->tmpDir);
        touch($this->tmpDir . '/deptrac-contexts.yaml');
        mkdir($this->tmpDir . '/src');

        $this->contractScanner = $this->createMock(ContractScanner::class);
        $this->deptracParser = $this->createMock(DeptracRulesetParser::class);
        $this->builder = $this->createMock(ContextMapBuilder::class);
        $this->yamlWriter = $this->createMock(YamlWriter::class);
        $this->mermaidWriter = $this->createMock(MermaidWriter::class);
        $this->configLoader = $this->createMock(ContextMapConfigLoader::class);

        $this->config = new ContextMapConfig(
            srcDir: $this->tmpDir . '/src',
            deptracFile: $this->tmpDir . '/deptrac-contexts.yaml',
            outputYaml: $this->tmpDir . '/contextmap.yaml',
            outputMermaid: $this->tmpDir . '/docs/context-map.md',
            excludedLayers: ['Shared', 'Vendor'],
        );

        $this->configLoader->method('load')->willReturn($this->config);
        $this->contractScanner->method('scan')->willReturn([]);
        $this->deptracParser->method('parse')->willReturn([]);
        $this->builder->method('build')->willReturn(['version' => '1.0', 'contexts' => []]);

        $this->tester = new CommandTester(new GenerateContextMapCommand(
            $this->contractScanner,
            $this->deptracParser,
            $this->builder,
            $this->yamlWriter,
            $this->mermaidWriter,
            $this->configLoader,
            $this->tmpDir,
        ));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->removeDir($f) : unlink($f);
        }
        rmdir($dir);
    }

    #[Test]
    public function itReturnsSuccessAndWritesBothFiles(): void
    {
        $this->yamlWriter->expects(self::once())->method('write');
        $this->mermaidWriter->expects(self::once())->method('write');

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Generated', $this->tester->getDisplay());
    }

    #[Test]
    public function itPassesExcludedLayersToParser(): void
    {
        $this->deptracParser
            ->expects(self::once())
            ->method('parse')
            ->with($this->config->getDeptracFile(), ['Shared', 'Vendor']);

        $this->tester->execute([]);
    }

    #[Test]
    public function itOverridesOutputYamlFromOption(): void
    {
        $customPath = $this->tmpDir . '/custom.yaml';
        $this->yamlWriter
            ->expects(self::once())
            ->method('write')
            ->with(self::anything(), $this->tmpDir . '/' . 'custom.yaml');

        $this->tester->execute(['--output-yaml' => 'custom.yaml']);
    }

    #[Test]
    public function itReturnsFailureWhenDeptracFileMissing(): void
    {
        unlink($this->tmpDir . '/deptrac-contexts.yaml');

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('deptrac-contexts.yaml not found', $this->tester->getDisplay());
    }

    #[Test]
    public function itReturnsFailureWhenSrcDirMissing(): void
    {
        rmdir($this->tmpDir . '/src');

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('src directory not found', $this->tester->getDisplay());
    }
}
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
make unit-test 2>&1 | grep -E "GenerateContextMap|ERROR" | head -10
```

- [ ] **Step 3: Create GenerateContextMapCommand**

Create `src/Shared/UI/Console/GenerateContextMapCommand.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\UI\Console;

use App\Shared\Infrastructure\ContextMap\ContextMapBuilder;
use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use App\Shared\Infrastructure\ContextMap\ContractScanner;
use App\Shared\Infrastructure\ContextMap\DeptracRulesetParser;
use App\Shared\Infrastructure\ContextMap\MermaidWriter;
use App\Shared\Infrastructure\ContextMap\YamlWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:contextmap:generate', description: 'Generate contextmap.yaml and docs/context-map.md from source')]
final class GenerateContextMapCommand extends Command
{
    public function __construct(
        private readonly ContractScanner $contractScanner,
        private readonly DeptracRulesetParser $deptracRulesetParser,
        private readonly ContextMapBuilder $contextMapBuilder,
        private readonly YamlWriter $yamlWriter,
        private readonly MermaidWriter $mermaidWriter,
        private readonly ContextMapConfigLoader $configLoader,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output-yaml', null, InputOption::VALUE_OPTIONAL, 'Output path for contextmap.yaml (relative to project dir)')
            ->addOption('output-mermaid', null, InputOption::VALUE_OPTIONAL, 'Output path for context-map.md (relative to project dir)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->configLoader->load($this->projectDir);

        if (!file_exists($config->getDeptracFile())) {
            $output->writeln('<error>deptrac-contexts.yaml not found</error>');

            return Command::FAILURE;
        }

        if (!is_dir($config->getSrcDir())) {
            $output->writeln(sprintf('<error>src directory not found: %s</error>', $config->getSrcDir()));

            return Command::FAILURE;
        }

        $outputYaml = null !== $input->getOption('output-yaml')
            ? $this->projectDir . '/' . $input->getOption('output-yaml')
            : $config->getOutputYaml();

        $outputMermaid = null !== $input->getOption('output-mermaid')
            ? $this->projectDir . '/' . $input->getOption('output-mermaid')
            : $config->getOutputMermaid();

        $contracts = $this->contractScanner->scan($config->getSrcDir());
        $consumes = $this->deptracRulesetParser->parse($config->getDeptracFile(), $config->getExcludedLayers());
        $map = $this->contextMapBuilder->build($contracts, $consumes);

        $this->yamlWriter->write($map, $outputYaml);
        $this->mermaidWriter->write($map, $outputMermaid);

        $output->writeln(sprintf('<info>Generated %s and %s</info>', basename($outputYaml), basename($outputMermaid)));

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Wire $projectDir in config/services/shared.yaml**

In `config/services/shared.yaml`, add after the existing `GenerateEventsCatalogCommand` entry:

```yaml
    App\Shared\UI\Console\GenerateContextMapCommand:
        arguments:
            $projectDir: '%kernel.project_dir%'
```

- [ ] **Step 5: Run tests to confirm pass**

```bash
make unit-test 2>&1 | grep -E "GenerateContextMap" | head -10
```

Expected: 5 tests pass.

- [ ] **Step 6: Run static analysis**

```bash
make static-code-analysis 2>&1 | tail -5
```

Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/Shared/UI/Console/GenerateContextMapCommand.php \
        tests/ContextMap/GenerateContextMapCommandTest.php \
        config/services/shared.yaml
git commit -m "feat(contextmap): add GenerateContextMapCommand"
```

---

## Task 8: CheckContextMapCommand

**Files:**
- Create: `src/Shared/UI/Console/CheckContextMapCommand.php`
- Create: `tests/ContextMap/CheckContextMapCommandTest.php`
- Modify: `config/services/shared.yaml`

- [ ] **Step 1: Write the failing test**

Create `tests/ContextMap/CheckContextMapCommandTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\ContextMap;

use App\Shared\Infrastructure\ContextMap\ContextMapChecker;
use App\Shared\Infrastructure\ContextMap\ContextMapConfig;
use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use App\Shared\UI\Console\CheckContextMapCommand;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('unit')]
class CheckContextMapCommandTest extends TestCase
{
    private string $tmpDir;
    private ContextMapChecker&MockObject $checker;
    private ContextMapConfigLoader&MockObject $configLoader;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cmcheck_' . uniqid();
        mkdir($this->tmpDir);

        $this->checker = $this->createMock(ContextMapChecker::class);
        $this->configLoader = $this->createMock(ContextMapConfigLoader::class);

        $config = new ContextMapConfig(
            srcDir: $this->tmpDir . '/src',
            deptracFile: $this->tmpDir . '/deptrac-contexts.yaml',
            outputYaml: $this->tmpDir . '/contextmap.yaml',
            outputMermaid: $this->tmpDir . '/docs/context-map.md',
            excludedLayers: [],
        );
        $this->configLoader->method('load')->willReturn($config);

        $this->tester = new CommandTester(new CheckContextMapCommand(
            $this->checker,
            $this->configLoader,
            $this->tmpDir,
        ));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->tmpDir);
    }

    #[Test]
    public function itReturnsFailureWhenContextmapYamlMissing(): void
    {
        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('contextmap.yaml not found', $this->tester->getDisplay());
        self::assertStringContainsString('app:contextmap:generate', $this->tester->getDisplay());
    }

    #[Test]
    public function itReturnsSuccessWhenAllChecksPass(): void
    {
        touch($this->tmpDir . '/contextmap.yaml');
        $this->checker->method('check')->willReturn(['ok' => ['Booker.BookerFinderInterface — class exists'], 'fail' => []]);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('[OK]', $this->tester->getDisplay());
    }

    #[Test]
    public function itReturnsFailureWhenChecksHaveFails(): void
    {
        touch($this->tmpDir . '/contextmap.yaml');
        $this->checker->method('check')->willReturn([
            'ok' => [],
            'fail' => ['Booker.MissingInterface — not found'],
        ]);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('[FAIL]', $this->tester->getDisplay());
    }
}
```

- [ ] **Step 2: Run tests to confirm failure**

```bash
make unit-test 2>&1 | grep -E "CheckContextMap|ERROR" | head -10
```

- [ ] **Step 3: Create CheckContextMapCommand**

Create `src/Shared/UI/Console/CheckContextMapCommand.php`:

```php
<?php
declare(strict_types=1);

namespace App\Shared\UI\Console;

use App\Shared\Infrastructure\ContextMap\ContextMapChecker;
use App\Shared\Infrastructure\ContextMap\ContextMapConfigLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:contextmap:check', description: 'Validate contextmap.yaml against source')]
final class CheckContextMapCommand extends Command
{
    public function __construct(
        private readonly ContextMapChecker $checker,
        private readonly ContextMapConfigLoader $configLoader,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->configLoader->load($this->projectDir);

        if (!file_exists($config->getOutputYaml())) {
            $output->writeln('<error>contextmap.yaml not found. Run: app:contextmap:generate</error>');

            return Command::FAILURE;
        }

        $result = $this->checker->check($config->getOutputYaml(), $config->getSrcDir());

        foreach ($result['ok'] as $msg) {
            $output->writeln(sprintf('<info>[OK]   %s</info>', $msg));
        }
        foreach ($result['fail'] as $msg) {
            $output->writeln(sprintf('<error>[FAIL] %s</error>', $msg));
        }

        if ([] !== $result['fail']) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Wire $projectDir in config/services/shared.yaml**

Add after the `GenerateContextMapCommand` entry:

```yaml
    App\Shared\UI\Console\CheckContextMapCommand:
        arguments:
            $projectDir: '%kernel.project_dir%'
```

- [ ] **Step 5: Run tests to confirm pass**

```bash
make unit-test 2>&1 | grep -E "CheckContextMap" | head -10
```

Expected: 3 tests pass.

- [ ] **Step 6: Run static analysis**

```bash
make static-code-analysis 2>&1 | tail -5
```

Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/Shared/UI/Console/CheckContextMapCommand.php \
        tests/ContextMap/CheckContextMapCommandTest.php \
        config/services/shared.yaml
git commit -m "feat(contextmap): add CheckContextMapCommand"
```

---

## Task 9: Cleanup — delete old files, update Makefile and composer.json

**Files:**
- Delete: `tools/ContextMap/ContractScanner.php`, `DeptracRulesetParser.php`, `ContextMapBuilder.php`, `YamlWriter.php`, `MermaidWriter.php`, `ContextMapChecker.php`
- Delete: `bin/generate-contextmap.php`, `bin/check-contextmap.php`
- Modify: `composer.json`
- Modify: `Makefile`

- [ ] **Step 1: Delete tool classes and bin scripts**

```bash
rm tools/ContextMap/ContractScanner.php \
   tools/ContextMap/DeptracRulesetParser.php \
   tools/ContextMap/ContextMapBuilder.php \
   tools/ContextMap/YamlWriter.php \
   tools/ContextMap/MermaidWriter.php \
   tools/ContextMap/ContextMapChecker.php \
   bin/generate-contextmap.php \
   bin/check-contextmap.php
rmdir tools/ContextMap
```

- [ ] **Step 2: Remove Tools autoload-dev from composer.json**

In `composer.json`, in the `autoload-dev.psr-4` section, remove:

```json
"Tools\\": "tools/",
```

The resulting `autoload-dev` block should be:

```json
"autoload-dev": {
    "psr-4": {
        "App\\Tests\\": "tests/"
    }
},
```

- [ ] **Step 3: Regenerate autoloader**

```bash
make unit-test 2>&1 | tail -5
```

Wait — the autoloader needs regeneration. Run inside Docker:

```bash
docker compose run --rm --no-deps php composer dump-autoload
```

- [ ] **Step 4: Update Makefile targets**

In `Makefile`, replace:

```makefile
contextmap: ## Generate contextmap.yaml and docs/context-map.md from source
	$(DOCKER_COMPOSE_RUN) --no-deps php php bin/generate-contextmap.php

contextmap-check: ## Validate contextmap.yaml against source code
	$(DOCKER_COMPOSE_RUN) --no-deps php php bin/check-contextmap.php
```

with:

```makefile
contextmap: ## Generate contextmap.yaml and docs/context-map.md from source
	$(DOCKER_COMPOSE_RUN) --no-deps php php bin/console app:contextmap:generate

contextmap-check: ## Validate contextmap.yaml against source code
	$(DOCKER_COMPOSE_RUN) --no-deps php php bin/console app:contextmap:check
```

- [ ] **Step 5: Run all unit tests**

```bash
make unit-test 2>&1 | tail -10
```

Expected: all tests pass, no class-not-found errors.

- [ ] **Step 6: Run lint**

```bash
make lint 2>&1 | tail -20
```

Expected: no errors.

- [ ] **Step 7: Smoke test the commands**

```bash
make contextmap
make contextmap-check
```

Expected: `Generated contextmap.yaml and context-map.md` then `[OK]` lines, exit 0.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore(contextmap): remove standalone scripts, update Makefile and composer.json"
```
