<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Persistence\Doctrine;

use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class BookerRepository implements BookerRepositoryInterface
{
    public function __construct(
        private Connection $bookerConnection,
    ) {
    }

    public function add(Booker $booker): void
    {
        $this->bookerConnection->insert('booker', [
            'id' => $booker->id,
            'first_name' => $booker->firstName,
            'last_name' => $booker->lastName,
            'email' => $booker->email,
            'phone' => $booker->phone,
            'date_of_birth' => $booker->dateOfBirth->format('Y-m-d'),
            'registered_at' => $booker->registeredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Booker
    {
        /** @var array{id: string, first_name: string, last_name: string, email: string, phone: string, date_of_birth: string, registered_at: string}|false $row */
        $row = $this->bookerConnection->fetchAssociative(
            'SELECT id, first_name, last_name, email, phone, date_of_birth, registered_at FROM booker WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Booker(
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            new \DateTimeImmutable($row['date_of_birth']),
            new \DateTimeImmutable($row['registered_at']),
        );
    }

    public function existsByEmail(string $email): bool
    {
        /** @var int|string $count */
        $count = $this->bookerConnection->fetchOne(
            'SELECT COUNT(*) FROM booker WHERE LOWER(email) = LOWER(:email)',
            ['email' => $email],
        );

        return (int) $count > 0;
    }
}
