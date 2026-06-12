<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

use Symfony\Component\Yaml\Yaml;

final class YamlWriter
{
    /**
     * @param array{version: string, generated_at: string, contexts: array<string, array{open_host_services: array{interfaces: string[], published_language: string[]}, consumed_by: string[], consumes: array<array{context: string}>}>} $contextMap
     */
    public function write(array $contextMap, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $header = "# Generated — do not edit manually. Run: make contextmap\n";
        file_put_contents($outputPath, $header . Yaml::dump($contextMap, 6, 2));
    }
}
