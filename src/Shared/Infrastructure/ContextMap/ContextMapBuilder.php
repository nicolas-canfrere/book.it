<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\ContextMap;

final class ContextMapBuilder
{
    /**
     * @param array<string, array{interfaces: string[], published_language: string[]}> $contracts
     * @param array<string, string[]> $consumes
     */
    public function build(array $contracts, array $consumes): array
    {
        $consumedBy = [];
        foreach ($consumes as $consumer => $producers) {
            foreach ($producers as $producer) {
                $consumedBy[$producer][] = $consumer;
            }
        }

        $allProducers = [];
        foreach ($consumes as $deps) {
            foreach ($deps as $dep) {
                $allProducers[] = $dep;
            }
        }

        $allContexts = array_unique(array_merge(
            array_keys($contracts),
            array_keys($consumes),
            $allProducers
        ));
        sort($allContexts);

        $contexts = [];
        foreach ($allContexts as $context) {
            $contexts[$context] = [
                'open_host_services' => [
                    'interfaces' => $contracts[$context]['interfaces'] ?? [],
                    'published_language' => $contracts[$context]['published_language'] ?? [],
                ],
                'consumed_by' => $consumedBy[$context] ?? [],
                'consumes' => array_map(
                    static fn(string $p): array => ['context' => $p],
                    $consumes[$context] ?? []
                ),
            ];
        }

        return [
            'version' => '1.0',
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'contexts' => $contexts,
        ];
    }
}
