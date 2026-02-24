<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the AI provider fails to produce a valid summary.
 * Handled globally in bootstrap/app.php → RFC 7807 Problem Details (HTTP 422).
 */
class AiProcessingException extends RuntimeException
{
    public static function fromThrowable(\Throwable $previous): self
    {
        return new self($previous->getMessage(), previous: $previous);
    }
}
