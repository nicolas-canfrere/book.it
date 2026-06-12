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

        if (!is_array($map) || !isset($map['contexts']) || !is_array($map['contexts'])) {
            $fail[] = 'Invalid contextmap: missing or invalid contexts key';
            return ['ok' => $ok, 'fail' => $fail];
        }

        foreach ($map['contexts'] as $context => $data) {
            if (!is_array($data)) {
                continue;
            }

            if (isset($data['open_host_services']) && is_array($data['open_host_services'])) {
                if (isset($data['open_host_services']['interfaces']) && is_array($data['open_host_services']['interfaces'])) {
                    foreach ($data['open_host_services']['interfaces'] as $fqcn) {
                        if (is_string($fqcn) && is_string($context)) {
                            $this->checkClass($srcDir, $context, $fqcn, $ok, $fail);
                        }
                    }
                }

                if (isset($data['open_host_services']['published_language']) && is_array($data['open_host_services']['published_language'])) {
                    foreach ($data['open_host_services']['published_language'] as $fqcn) {
                        if (is_string($fqcn) && is_string($context)) {
                            $this->checkClass($srcDir, $context, $fqcn, $ok, $fail);
                        }
                    }
                }
            }

            if (isset($data['consumes']) && is_array($data['consumes'])) {
                foreach ($data['consumes'] as $relation) {
                    if (!is_array($relation) || !isset($relation['context'])) {
                        continue;
                    }
                    $producer = $relation['context'];
                    if (is_string($producer) && is_string($context)) {
                        if ($this->hasAdapterFor($srcDir, $context, $producer)) {
                            $ok[] = "{$context} consumes {$producer} — adapter found";
                        } else {
                            $fail[] = "{$context} consumes {$producer} — no adapter found in {$context}/Infrastructure/";
                        }
                    }
                }
            }
        }

        return ['ok' => $ok, 'fail' => $fail];
    }

    /**
     * @param string[] &$ok
     * @param string[] &$fail
     */
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
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }
            if (str_contains($contents, 'App\\' . $producer . '\\Application\\Contract\\')) {
                return true;
            }
        }

        return false;
    }
}
