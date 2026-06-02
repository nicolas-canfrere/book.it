<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Bus\Middleware;

use Symfony\Component\Messenger\Envelope;

final class EnvelopeCapture
{
    public ?Envelope $envelope = null;
}
