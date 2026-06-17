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
            'message' => ['nullable', 'required_without:template', 'string', 'max:4096'],
            'template' => ['nullable', 'string'],
            'language' => ['nullable', 'string'],
            'parameters' => ['nullable', 'array'],
        ]);

        if (! empty($data['template'])) {
            SendWhatsAppMessageJob::dispatch([
                'message_type' => 'template',
                'phone' => $data['phone'],
                'template' => $data['template'],
                'language' => $data['language'] ?? 'es_MX',
                'parameters' => $data['parameters'] ?? [],
            ]);
        } else {
            SendWhatsAppMessageJob::dispatch([
                'message_type' => 'text',
                'phone' => $data['phone'],
                'message' => $data['message'],
            ]);
        }

        return response()->json([
            'message' => 'Mensaje WhatsApp encolado para envio.',
        ], 202);
    }
}
