<?php

namespace App\Application\Actions;

use App\Contracts\AiProvider;
use App\Domain\Enums\MessageStatus;
use App\Domain\ValueObjects\Summary;
use App\Events\MessageProcessed;
use App\Exceptions\AiProcessingException;
use App\Models\Message;

/**
 * Orchestrates the main processing flow:
 *   1. Call the AI provider to obtain a ≤100-char summary.
 *   2. Persist the message with its summary.
 *   3. Fire MessageProcessed so the channel dispatcher takes over.
 *
 * If the AI call fails, a RuntimeException propagates and nothing is persisted,
 * satisfying the requirement: "Si la IA falla, no hay envío."
 */
class ProcessMessageAction
{
    public function __construct(private readonly AiProvider $aiProvider) {}

    /**
     * @param  list<string>  $channels  Channel enum values (e.g. ['email', 'slack'])
     *
     * @throws AiProcessingException when the AI provider fails or returns an invalid summary.
     */
    public function execute(string $title, string $content, array $channels): Message
    {
        // Step 1 – Summarization.
        // If the content already fits within the Summary limit, use it directly
        // without calling the AI (no tokens wasted, no latency added).
        // Otherwise, delegate to the AI provider; on failure persist a failed
        // record for traceability and re-throw.
        if (mb_strlen(trim($content)) <= Summary::MAX_LENGTH) {
            $summary = Summary::fromString($content);
        } else {
            try {
                $summary = $this->aiProvider->summarize($content);
            } catch (\RuntimeException $e) {
                Message::create([
                    'title' => $title,
                    'original_content' => $content,
                    'summary' => null,
                    'channels' => $channels,
                    'status' => MessageStatus::Failed,
                ]);

                throw AiProcessingException::fromThrowable($e);
            }
        }

        // Step 2 – Persist with initial "pending" status.
        $message = Message::create([
            'title' => $title,
            'original_content' => $content,
            'summary' => $summary->value(),
            'channels' => $channels,
            'status' => MessageStatus::Pending,
        ]);

        // Step 3 – Notify listeners (DispatchChannelsListener → DispatchToChannelsAction).
        MessageProcessed::dispatch($message);

        return $message;
    }
}
