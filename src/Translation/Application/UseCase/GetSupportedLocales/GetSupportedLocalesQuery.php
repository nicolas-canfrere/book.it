<?php

declare(strict_types=1);

namespace App\Translation\Application\UseCase\GetSupportedLocales;

use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<SupportedLocalesView> */
final readonly class GetSupportedLocalesQuery implements SyncQueryInterface
{
}
