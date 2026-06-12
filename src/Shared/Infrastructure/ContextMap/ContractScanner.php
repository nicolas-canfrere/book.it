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
            if (!preg_match('#/([^/]+)/Application/Contract$#', $contractDir, $matches)) {
                continue;
            }
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
