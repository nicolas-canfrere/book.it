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
