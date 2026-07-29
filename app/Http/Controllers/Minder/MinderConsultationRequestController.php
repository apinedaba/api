<?php

namespace App\Http\Controllers\Minder;

use App\Http\Controllers\Controller;
use App\Models\MinderConsultationRequest;
use App\Models\RedRespuesta;
use App\Models\User;
use App\Notifications\MinderConsultationRequestNotification;
use App\Services\MinderDirectMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MinderConsultationRequestController extends Controller
{
    public function __construct(private readonly MinderDirectMessageService $directMessages)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $with = ['sender:id,name,image,personales', 'recipient:id,name,image,personales', 'question:id,titulo', 'group'];

        $received = MinderConsultationRequest::with($with)
            ->where('recipient_id', $userId)
            ->latest()
            ->limit(50)
            ->get();

        $sent = MinderConsultationRequest::with($with)
            ->where('sender_id', $userId)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'received' => $received,
            'sent' => $sent,
            'pending_received_count' => $received->where('status', MinderConsultationRequest::STATUS_PENDING)->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $sender = $request->user();
        $validated = $request->validate([
            'recipient_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::notIn([$sender->id]),
            ],
            'red_pregunta_id' => 'nullable|integer|exists:red_preguntas,id',
            'red_respuesta_id' => 'nullable|integer|exists:red_respuestas,id',
            'subject' => 'required|string|min:5|max:160',
            'message' => 'required|string|min:20|max:2000',
            'privacy_acknowledged' => 'accepted',
        ]);

        $recipient = User::findOrFail($validated['recipient_user_id']);
        abort_if(
            $recipient->identity_verification_status !== 'approved' || ! $recipient->activo,
            422,
            'El destinatario no es un psicólogo verificado activo.'
        );

        if (! empty($validated['red_respuesta_id'])) {
            $answer = RedRespuesta::findOrFail($validated['red_respuesta_id']);
            abort_if($answer->user_id !== $recipient->id, 422, 'La respuesta no pertenece al destinatario.');
            $validated['red_pregunta_id'] = $answer->pregunta_id;
        }

        $alreadyPending = MinderConsultationRequest::where('sender_id', $sender->id)
            ->where('recipient_id', $recipient->id)
            ->where('status', MinderConsultationRequest::STATUS_PENDING)
            ->exists();
        abort_if($alreadyPending, 422, 'Ya tienes una solicitud pendiente con este profesional.');

        $consultation = MinderConsultationRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'red_pregunta_id' => $validated['red_pregunta_id'] ?? null,
            'red_respuesta_id' => $validated['red_respuesta_id'] ?? null,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ]);

        $consultation->load('sender:id,name,image', 'recipient:id,name,image', 'question:id,titulo');
        $recipient->notify(new MinderConsultationRequestNotification($consultation, 'requested'));

        return response()->json(['data' => $consultation, 'message' => 'Solicitud enviada.'], 201);
    }

    public function accept(Request $request, MinderConsultationRequest $consultationRequest): JsonResponse
    {
        abort_if($consultationRequest->recipient_id !== $request->user()->id, 403);
        abort_if($consultationRequest->status !== MinderConsultationRequest::STATUS_PENDING, 422, 'La solicitud ya fue resuelta.');

        $result = DB::transaction(function () use ($consultationRequest, $request) {
            $directMessage = $this->directMessages->findOrCreate($consultationRequest->sender, $request->user());
            $consultationRequest->update([
                'status' => MinderConsultationRequest::STATUS_ACCEPTED,
                'minder_group_id' => $directMessage['group']->id,
                'accepted_at' => now(),
                'resolved_at' => now(),
            ]);

            return $directMessage;
        });

        $consultationRequest->load('sender:id,name,image', 'recipient:id,name,image', 'question:id,titulo', 'group');
        $consultationRequest->sender->notify(new MinderConsultationRequestNotification($consultationRequest, 'accepted'));

        return response()->json([
            'data' => $consultationRequest,
            'group' => $result['group'],
            'message' => 'Solicitud aceptada. Ya pueden conversar.',
        ]);
    }

    public function reject(Request $request, MinderConsultationRequest $consultationRequest): JsonResponse
    {
        abort_if($consultationRequest->recipient_id !== $request->user()->id, 403);
        abort_if($consultationRequest->status !== MinderConsultationRequest::STATUS_PENDING, 422, 'La solicitud ya fue resuelta.');

        $consultationRequest->update([
            'status' => MinderConsultationRequest::STATUS_REJECTED,
            'resolved_at' => now(),
        ]);
        $consultationRequest->load('sender:id,name,image', 'recipient:id,name,image');
        $consultationRequest->sender->notify(new MinderConsultationRequestNotification($consultationRequest, 'rejected'));

        return response()->json(['data' => $consultationRequest, 'message' => 'Solicitud rechazada.']);
    }

    public function cancel(Request $request, MinderConsultationRequest $consultationRequest): JsonResponse
    {
        abort_if($consultationRequest->sender_id !== $request->user()->id, 403);
        abort_if($consultationRequest->status !== MinderConsultationRequest::STATUS_PENDING, 422, 'La solicitud ya fue resuelta.');

        $consultationRequest->update([
            'status' => MinderConsultationRequest::STATUS_CANCELLED,
            'resolved_at' => now(),
        ]);

        return response()->json(['data' => $consultationRequest, 'message' => 'Solicitud cancelada.']);
    }
}
