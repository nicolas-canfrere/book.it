<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\GetBooker;

use App\Booker\Domain\Model\Booker;
use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<Booker|null>
 */
final readonly class GetBookerQuery implements SyncQueryInterface
{
    public function __construct(public string $bookerId)
    {
    }
}
