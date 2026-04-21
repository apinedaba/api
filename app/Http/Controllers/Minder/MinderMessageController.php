<?php

namespace App\Http\Controllers\Minder;

use App\Events\MinderMessageSent;
use App\Http\Controllers\Controller;
use App\Models\MinderGroup;
use App\Models\MinderMessage;
use App\Models\MinderReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MinderMessageController extends Controller
{
    public function index(Request $request, MinderGroup $minderGroup): JsonResponse
    {
        $this->authorize('view', $minderGroup);

        $perPage = min((int) $request->integer('per_page', 30), 50);

        $messages = $minderGroup->messages()
            ->whereNull('parent_id')
            ->where('is_deleted', false)
            ->with([
                'user:id,name,image',
                'reactions',
            ])
            ->withCount('replies')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $messages->getCollection()->values(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'total'        => $messages->total(),
                'per_page'     => $messages->perPage(),
            ],
        ]);
    }

    public function store(Request $request, MinderGroup $minderGroup): JsonResponse
    {
        $this->authorize('sendMessage', $minderGroup);

        $validated = $request->validate([
            'body' => 'required|string|max:4000',
        ]);

        $message = $minderGroup->messages()->create([
            'user_id'   => $request->user()->id,
            'body'      => $validated['body'],
            'parent_id' => null,
        ]);

        $message->load('user:id,name,image');

        broadcast(new MinderMessageSent($message))->toOthers();

        return response()->json(['data' => $message], 201);
    }

    public function thread(Request $request, MinderGroup $minderGroup, MinderMessage $message): JsonResponse
    {
        $this->authorize('view', $minderGroup);

        abort_if($message->group_id !== $minderGroup->id, 404);

        $replies = $message->replies()
            ->where('is_deleted', false)
            ->with('user:id,name,image', 'reactions')
            ->oldest()
            ->get();

        return response()->json(['data' => $replies, 'parent' => $message->load('user:id,name,image')]);
    }

    public function storeThread(Request $request, MinderGroup $minderGroup, MinderMessage $message): JsonResponse
    {
        $this->authorize('sendMessage', $minderGroup);

        abort_if($message->group_id !== $minderGroup->id, 404);
        abort_if(! is_null($message->parent_id), 422, 'No se puede responder a una respuesta de hilo.');

        $validated = $request->validate([
            'body' => 'required|string|max:4000',
        ]);

        $reply = $minderGroup->messages()->create([
            'user_id'   => $request->user()->id,
            'body'      => $validated['body'],
            'parent_id' => $message->id,
        ]);

        $reply->load('user:id,name,image');

        broadcast(new MinderMessageSent($reply))->toOthers();

        return response()->json(['data' => $reply], 201);
    }

    public function report(Request $request, MinderGroup $minderGroup, MinderMessage $message): JsonResponse
    {
        $this->authorize('view', $minderGroup);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $alreadyReported = MinderReport::where('message_id', $message->id)
            ->where('reported_by', $request->user()->id)
            ->exists();

        if ($alreadyReported) {
            return response()->json(['message' => 'Ya reportaste este mensaje.'], 409);
        }

        MinderReport::create([
            'message_id'  => $message->id,
            'reported_by' => $request->user()->id,
            'reason'      => $validated['reason'],
        ]);

        return response()->json(['message' => 'Reporte enviado.']);
    }
}
