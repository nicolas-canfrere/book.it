<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Correlation;

use Symfony\Component\Uid\Uuid;

final class RequestCorrelationContext
{
    private string $id;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
