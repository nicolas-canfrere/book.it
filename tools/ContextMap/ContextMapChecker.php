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
