<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class SearchPathMiddleware implements Middleware
{
    public function __construct(private readonly string $schema)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        // Anonymous classes cannot capture $this from the outer scope; pass explicitly.
        $schema = $this->schema;

        return new class($driver, $schema) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly string $schema)
            {
                parent::__construct($driver);
            }

            public function connect(array $params): Connection
            {
                $connection = parent::connect($params);
                $connection->exec("SET search_path = \"{$this->schema}\"");

                return $connection;
            }
        };
    }
}
