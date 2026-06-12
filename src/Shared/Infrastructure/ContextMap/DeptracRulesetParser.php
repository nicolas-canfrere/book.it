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
