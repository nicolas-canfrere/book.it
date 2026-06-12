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
