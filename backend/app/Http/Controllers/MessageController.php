<?php

namespace App\Http\Controllers;

use App\Application\Actions\ProcessMessageAction;
use App\Http\Requests\ProcessMessageRequest;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

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
            $message->load('deliveryLogs'),
            201,
        );
    }

    /**
     * Return a paginated list of messages with their delivery logs.
     */
    public function index(): JsonResponse
    {
        $messages = Message::with('deliveryLogs')
            ->latest()
            ->paginate(15);

        return response()->json($messages);
    }

    /**
     * Return a single message with its delivery logs.
     */
    public function show(Message $message): JsonResponse
    {
        return response()->json($message->load('deliveryLogs'));
    }
}
