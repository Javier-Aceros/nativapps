<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

final class Summary
{
    public const MAX_LENGTH = 100;

    private function __construct(private readonly string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Summary cannot be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Summary must not exceed %d characters, got %d.', self::MAX_LENGTH, mb_strlen($trimmed))
            );
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
