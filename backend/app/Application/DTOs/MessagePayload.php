<?php

namespace App\Application\DTOs;

readonly class MessagePayload
{
    public function __construct(
        public string $title,
        public string $summary,
        public string $originalContent,
    ) {}

    /** Full payload (Email, Slack). */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'original_content' => $this->originalContent,
        ];
    }

    /** Reduced payload for SMS (omits original_content per spec). */
    public function toSmsArray(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
        ];
    }
}
