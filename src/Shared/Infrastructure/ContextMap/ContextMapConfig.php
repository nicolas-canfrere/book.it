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
