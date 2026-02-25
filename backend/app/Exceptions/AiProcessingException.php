<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the AI provider fails to produce a valid summary.
 * Handled globally in bootstrap/app.php → RFC 7807 Problem Details (HTTP 422).
 */
class AiProcessingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'ai_error',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function fromThrowable(\Throwable $previous): self
    {
        $errorCode = str_contains($previous->getMessage(), 'exceeds')
            ? 'ai_summary_too_long'
            : 'ai_error';

        return new self($previous->getMessage(), $errorCode, $previous);
    }
}
