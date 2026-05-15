<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final readonly class ExceptionProblemRegistry
{
    /**
     * @param array<class-string<\Throwable>, array{type: string, title: string, status: int}> $map
     */
    public function __construct(private array $map)
    {
    }

    /**
     * @return array{type: string, title: string, status: int}|null
     */
    public function resolve(\Throwable $exception): ?array
    {
        return $this->map[$exception::class] ?? null;
    }
}
