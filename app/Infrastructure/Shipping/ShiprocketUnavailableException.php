<?php
declare(strict_types=1);

namespace App\Infrastructure\Shipping;

use RuntimeException;

/**
 * A Shiprocket failure that is worth retrying: transport errors, 5xx, and
 * non-JSON bodies from the gateway. Distinguished from RuntimeException so the
 * caller can retry rather than treating it as a bad request.
 */
final class ShiprocketUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 0,
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}