<?php

namespace App\Http\Controllers\Minder;

use App\Events\MinderSupportMessageSent;
use App\Http\Controllers\Controller;
use App\Models\MinderSupportMessage;
use App\Models\MinderSupportThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MinderSupportController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $thread = MinderSupportThread::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'open']
        );

        $thread->load('messages.sender');

        return response()->json(['data' => $thread]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'body' => 'required|string|max:4000',
        ]);

        $thread = MinderSupportThread::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'open']
        );

        $message = MinderSupportMessage::create([
            'thread_id'   => $thread->id,
            'sender_type' => \App\Models\User::class,
            'sender_id'   => $user->id,
            'body'        => $validated['body'],
        ]);

        $thread->touch('last_message_at');
        $message->load('sender');

        broadcast(new MinderSupportMessageSent($message->load('thread')))->toOthers();

        return response()->json(['data' => $message], 201);
    }
}
