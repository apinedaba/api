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
            'components' => ['nullable', 'array'],
            'interactive' => ['nullable', 'boolean'],
            'header' => ['nullable', 'string', 'max:60'],
            'body' => ['nullable', 'required_if:interactive,true', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'buttons.*.id' => ['required_with:buttons', 'string', 'max:256'],
            'buttons.*.title' => ['required_with:buttons', 'string', 'max:20'],
        ]);

        if (! empty($data['interactive'])) {
            SendWhatsAppMessageJob::dispatch([
                'message_type' => 'interactive_buttons',
                'phone' => $data['phone'],
                'header' => $data['header'] ?? null,
                'body' => $data['body'],
                'footer' => $data['footer'] ?? null,
                'buttons' => $data['buttons'] ?? [],
            ]);
        } elseif (! empty($data['template'])) {
            SendWhatsAppMessageJob::dispatch([
                'message_type' => 'template',
                'phone' => $data['phone'],
                'template' => $data['template'],
                'language' => $data['language'] ?? 'es_MX',
                'parameters' => $data['parameters'] ?? [],
                'components' => $data['components'] ?? [],
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
