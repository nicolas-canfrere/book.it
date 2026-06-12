<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

use Symfony\Component\Yaml\Yaml;

final class DeptracRulesetParser
{
    /**
     * @param string[] $excludedLayers
     *
     * @return array<string, list<string>>
     */
    public function parse(
        string $deptracYamlPath,
        array $excludedLayers = ['Shared', 'Vendor', 'Payment', 'Search', 'Translation'],
    ): array {
        try {
            $data = Yaml::parseFile($deptracYamlPath);
        } catch (\Symfony\Component\Yaml\Exception\ParseException) {
            return [];
        }
        if (!is_array($data)) {
            return [];
        }
        $deptracConfig = $data['deptrac'] ?? [];
        if (!is_array($deptracConfig)) {
            return [];
        }
        $ruleset = $deptracConfig['ruleset'] ?? [];
        if (!is_array($ruleset)) {
            return [];
        }
        $result = [];

        foreach ($ruleset as $context => $dependencies) {
            if (!is_array($dependencies) || str_ends_with($context, 'Contract')) {
                continue;
            }
            if (in_array($context, $excludedLayers, true)) {
                continue;
            }

            $consumed = [];
            foreach ($dependencies as $dep) {
                if (!is_string($dep)) {
                    continue;
                }
                if (str_ends_with($dep, 'Contract') && $dep !== $context . 'Contract') {
                    $consumed[] = substr($dep, 0, -strlen('Contract'));
                }
            }

            if (is_string($context)) {
                $result[$context] = $consumed;
            }
        }

        return $result;
    }
}
