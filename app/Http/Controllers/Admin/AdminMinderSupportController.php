<?php

namespace App\Http\Controllers\Admin;

use App\Events\MinderSupportMessageSent;
use App\Http\Controllers\Controller;
use App\Models\MinderSupportMessage;
use App\Models\MinderSupportThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMinderSupportController extends Controller
{
    public function index(): Response
    {
        $threads = MinderSupportThread::with([
            'user:id,name,image',
            'messages' => fn($q) => $q->latest()->limit(1),
        ])
            ->withCount(['messages as unread_count' => fn($q) => $q->where('is_read', false)->where('sender_type', 'App\\Models\\User')])
            ->orderByDesc('last_message_at')
            ->paginate(25);

        return Inertia::render('Minder/Support', [
            'threads' => $threads,
        ]);
    }

    public function show(MinderSupportThread $thread): Response
    {
        $thread->load('user:id,name,image', 'messages.sender');

        MinderSupportMessage::where('thread_id', $thread->id)
            ->where('sender_type', 'App\\Models\\User')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return Inertia::render('Minder/SupportThread', [
            'thread' => $thread,
        ]);
    }

    public function store(Request $request, MinderSupportThread $thread): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:4000',
        ]);

        $message = MinderSupportMessage::create([
            'thread_id'   => $thread->id,
            'sender_type' => \App\Models\Administrator::class,
            'sender_id'   => auth()->id(),
            'body'        => $validated['body'],
        ]);

        $thread->touch('last_message_at');
        $message->load('thread', 'sender');

        broadcast(new MinderSupportMessageSent($message));

        return back()->with('success', 'Respuesta enviada.');
    }

    public function closeThread(MinderSupportThread $thread): RedirectResponse
    {
        $thread->update(['status' => 'closed']);

        return back()->with('success', 'Hilo cerrado.');
    }
}
