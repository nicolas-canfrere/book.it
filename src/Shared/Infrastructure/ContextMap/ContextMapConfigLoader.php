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
            if (is_array($parsed) && isset($parsed['contextmap']) && is_array($parsed['contextmap'])) {
                foreach ($parsed['contextmap'] as $key => $value) {
                    if (null !== $value && array_key_exists($key, $data)) {
                        /** @var string|array<string> $value */
                        $data[$key] = $value;
                    }
                }
            }
        }

        /** @var string $srcDir */
        $srcDir = $data['src_dir'];
        /** @var string $deptracFile */
        $deptracFile = $data['deptrac_file'];
        /** @var string $outputYaml */
        $outputYaml = $data['output_yaml'];
        /** @var string $outputMermaid */
        $outputMermaid = $data['output_mermaid'];
        /** @var array<string> $excludedLayers */
        $excludedLayers = $data['excluded_layers'];

        return new ContextMapConfig(
            srcDir: $projectDir . '/' . $srcDir,
            deptracFile: $projectDir . '/' . $deptracFile,
            outputYaml: $projectDir . '/' . $outputYaml,
            outputMermaid: $projectDir . '/' . $outputMermaid,
            excludedLayers: $excludedLayers,
        );
    }
}
