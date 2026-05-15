<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final readonly class ProblemDetail
{
    /**
     * @param list<array{field: string, message: string}>|null $violations
     */
    public function __construct(
        public string $type,
        public string $title,
        public int $status,
        public string $detail,
        public ?array $violations = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ];

        if (null !== $this->violations) {
            $data['violations'] = $this->violations;
        }

        return $data;
    }
}
