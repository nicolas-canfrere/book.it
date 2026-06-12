<?php
declare(strict_types=1);

namespace Tools\ContextMap;

final class MermaidWriter
{
    public function write(array $contextMap, string $outputPath): void
    {
        $lines = [
            '# Context Map',
            '',
            '> Generated — do not edit manually. Run: `make contextmap`',
            '',
            '```mermaid',
            'graph LR',
        ];

        foreach ($contextMap['contexts'] as $consumer => $data) {
            foreach ($data['consumes'] as $relation) {
                $producer = $relation['context'];
                $interfaces = $contextMap['contexts'][$producer]['open_host_services']['interfaces'] ?? [];
                $label = [] !== $interfaces ? $this->shortName($interfaces[0]) : $producer;
                $lines[] = "  {$consumer} -->|{$label}| {$producer}";
            }
        }

        $lines[] = '```';
        $lines[] = '';

        file_put_contents($outputPath, implode("\n", $lines));
    }

    private function shortName(string $fqcn): string
    {
        return substr($fqcn, strrpos($fqcn, '\\') + 1);
    }
}
