<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestWhatsAppController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(app()->environment('local'), 403, 'Endpoint disponible solo en ambiente local.');

        $data = $request->validate([
            'phone' => ['required', 'string'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        SendWhatsAppMessageJob::dispatch([
            'message_type' => 'text',
            'phone' => $data['phone'],
            'message' => $data['message'],
        ]);

        return response()->json([
            'message' => 'Mensaje WhatsApp encolado para envio.',
        ], 202);
    }
}
