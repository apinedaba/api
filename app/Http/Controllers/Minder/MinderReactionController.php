<?php

namespace App\Http\Controllers\Minder;

use App\Http\Controllers\Controller;
use App\Models\MinderGroup;
use App\Models\MinderMessage;
use App\Models\MinderMessageReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MinderReactionController extends Controller
{
    public function store(Request $request, MinderGroup $minderGroup, MinderMessage $message): JsonResponse
    {
        $this->authorize('view', $minderGroup);

        abort_if($message->group_id !== $minderGroup->id, 404);

        $validated = $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        $reaction = MinderMessageReaction::firstOrCreate([
            'message_id' => $message->id,
            'user_id'    => $request->user()->id,
            'emoji'      => $validated['emoji'],
        ]);

        $reactions = $message->reactions()->with('user:id,name')->get();

        return response()->json(['data' => $reactions], $reaction->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, MinderGroup $minderGroup, MinderMessage $message): JsonResponse
    {
        $this->authorize('view', $minderGroup);

        abort_if($message->group_id !== $minderGroup->id, 404);

        $validated = $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        MinderMessageReaction::where('message_id', $message->id)
            ->where('user_id', $request->user()->id)
            ->where('emoji', $validated['emoji'])
            ->delete();

        $reactions = $message->reactions()->with('user:id,name')->get();

        return response()->json(['data' => $reactions]);
    }
}
