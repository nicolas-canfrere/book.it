<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    /** @var array<string, string|int> $context */
    return new Kernel((string) $context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
