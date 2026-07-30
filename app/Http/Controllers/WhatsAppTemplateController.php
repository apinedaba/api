<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsAppTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => WhatsAppTemplate::query()
                ->orderBy('key')
                ->get(),
            'fallbacks' => config('services.whatsapp.templates', []),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateTemplate($request);

        $template = WhatsAppTemplate::create($data);

        return response()->json([
            'message' => 'Template WhatsApp creado.',
            'data' => $template,
        ], 201);
    }

    public function show(WhatsAppTemplate $whatsappTemplate): JsonResponse
    {
        return response()->json([
            'data' => $whatsappTemplate,
        ]);
    }

    public function update(Request $request, WhatsAppTemplate $whatsappTemplate): JsonResponse
    {
        $data = $this->validateTemplate($request, $whatsappTemplate);

        $whatsappTemplate->update($data);

        return response()->json([
            'message' => 'Template WhatsApp actualizado.',
            'data' => $whatsappTemplate->fresh(),
        ]);
    }

    public function destroy(WhatsAppTemplate $whatsappTemplate): JsonResponse
    {
        $whatsappTemplate->delete();

        return response()->json([
            'message' => 'Template WhatsApp eliminado.',
        ]);
    }

    protected function validateTemplate(Request $request, ?WhatsAppTemplate $template = null): array
    {
        $templateId = $template?->id;

        return $request->validate([
            'key' => [
                'required',
                'string',
                'max:100',
                Rule::unique('whatsapp_templates', 'key')->ignore($templateId),
            ],
            'template_name' => ['required', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'body_parameters' => ['nullable', 'array'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'buttons.*.id' => ['nullable', 'string', 'max:256'],
            'buttons.*.payload' => ['nullable', 'string', 'max:256'],
            'buttons.*.text' => ['nullable', 'string', 'max:256'],
            'buttons.*.sub_type' => ['nullable', Rule::in(['quick_reply', 'url'])],
            'buttons.*.parameter_type' => ['nullable', Rule::in(['payload', 'text'])],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
