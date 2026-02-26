<?php

namespace App\Http\Controllers;

use App\Application\Actions\ProcessMessageAction;
use App\Application\Actions\RetryChannelAction;
use App\Application\Actions\RetryMessageAction;
use App\Domain\Enums\Channel;
use App\Domain\Enums\MessageStatus;
use App\Http\Requests\ProcessMessageRequest;
use App\Models\DeliveryLog;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private readonly ProcessMessageAction $action) {}

    /**
     * Process and store a new message.
     *
     * Flow: validate → AI summarize → persist → dispatch channels.
     * Returns 201 with the created message (including seeded delivery logs).
     */
    public function store(ProcessMessageRequest $request): JsonResponse
    {
        $message = $this->action->execute(
            title: $request->validated('title'),
            content: $request->validated('content'),
            channels: $request->validated('channels'),
        );

        return response()->json(
            $this->loadLatestLogs($message),
            201,
        );
    }

    /**
     * Return a paginated list of messages with their latest delivery log per channel.
     */
    public function index(Request $request): JsonResponse
    {
        $allowed = [5, 10, 15, 25, 50];
        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, $allowed, true) ? $perPage : 15;

        $messages = Message::with(['deliveryLogs' => function ($q) {
            $q->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('delivery_logs')
                    ->groupBy('message_id', 'channel');
            });
        }])->latest()->paginate($perPage);

        return response()->json($messages);
    }

    /**
     * Return a single message with its latest delivery log per channel.
     */
    public function show(Message $message): JsonResponse
    {
        return response()->json($this->loadLatestLogs($message));
    }

    /**
     * Retry the full AI + channel dispatch flow for a failed message.
     * Returns the updated message (with latest delivery logs) regardless of outcome.
     */
    public function retryAi(Message $message, RetryMessageAction $action): JsonResponse
    {
        if ($message->status === MessageStatus::Processing) {
            return response()->json(['message' => 'El mensaje está siendo procesado actualmente.'], 409);
        }

        return response()->json($this->loadLatestLogs($action->execute($message)));
    }

    /**
     * Retry delivery for a single channel of an already-processed message.
     * Returns the new DeliveryLog immediately (job runs asynchronously).
     */
    public function retryChannel(Message $message, string $channel, RetryChannelAction $action): JsonResponse
    {
        $channelEnum = Channel::tryFrom($channel);

        if ($channelEnum === null) {
            return response()->json(['message' => "Canal no válido: {$channel}"], 422);
        }

        try {
            return response()->json($action->execute($message, $channelEnum));
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'already being retried') ? 409 : 422;

            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Load only the latest attempt per channel for a message.
     */
    private function loadLatestLogs(Message $message): Message
    {
        return $message->load(['deliveryLogs' => function ($q) {
            $q->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('delivery_logs')
                    ->groupBy('message_id', 'channel');
            });
        }]);
    }
}
