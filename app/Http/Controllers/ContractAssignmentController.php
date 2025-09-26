<?php
namespace App\Http\Controllers;

use App\Models\ContractAssignment;
use App\Models\ContractTemplate;
use App\Services\ContractRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf; // dompdf wrapper

class ContractAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = ContractAssignment::query();
        if ($user && property_exists($user, 'account_id'))
            $q->where('account_id', $user->account_id);
        if ($request->boolean('me') && $user)
            $q->where('patient_id', $user->id);
        if ($pid = $request->input('patient_id'))
            $q->where('patient_id', $pid);
        $q->orderByDesc('id');
        return $q->paginate(20);
    }
    public function store(Request $request, ContractRenderService $render)
    {
        $data = $request->validate([
            'template_id' => 'required|string',
            'patient_id' => 'required|integer',
            'payload' => 'array',
        ]);

        $tpl = ContractTemplate::findOrFail($data['template_id']);
        $payload = $this->normalizePayload($data['payload'] ?? []);
        $rendered = $render->renderHtmlWithPayload($tpl->html, $payload);

        $a = ContractAssignment::create([
            'account_id' => optional($request->user())->account_id,
            'template_id' => $tpl->id,
            'patient_id' => $data['patient_id'],
            'status' => 'pending',
            'payload_json' => $payload,
            'rendered_html' => $rendered,
        ]);

        // Opcional: devolver template embebido para el front
        $a->load('template');

        return response()->json($a, 201);
    }
    private function normalizePayload(array $flat): array
    {
        $out = [];
        foreach ($flat as $k => $v) {
            // data_set convierte "paciente.nombre" => ["paciente" => ["nombre" => v]]
            data_set($out, $k, $v);
        }
        return $out;
    }
    public function show(Request $request, $id)
    {
        $a = ContractAssignment::findOrFail($id);
        $this->authorizeView($request, $a);
        return $a;
    }

    /** Recibe firma (base64 PNG o archivo) y sube a Cloudinary */
    public function signature(Request $request, $id)
    {
        $a = ContractAssignment::findOrFail($id);
        $this->authorizeView($request, $a);

        $request->validate(['signature' => 'required']);
        $raw = $request->input('signature');

        // Soporta multipart file o dataURL base64
        if ($request->hasFile('signature') && $request->file('signature')->isValid()) {
            $bin = file_get_contents($request->file('signature')->getRealPath());
        } else {
            if (preg_match('/^data:image\/(png|jpeg);base64,/', $raw)) {
                $raw = substr($raw, strpos($raw, ',') + 1);
            }
            $bin = base64_decode($raw);
        }

        $dir = 'signatures/' . date('Ymd');
        $name = $a->id . '-' . Str::random(6) . '.png';
        $path = $dir . '/' . $name;

        Storage::disk('local')->put($path, $bin);

        // Guarda el path local; deja signature_url opcional en null
        $a->signature_path = $path;
        $a->signature_url = null;
        $a->save();

        return $a->fresh();
    }

    public function finalize(Request $request, $id, ContractRenderService $render)
    {
        $a = ContractAssignment::with('template')->findOrFail($id);
        $this->authorizeView($request, $a);

        if (!$a->signature_path || !Storage::disk('local')->exists($a->signature_path)) {
            return response()->json(['message' => 'Falta la firma'], 422);
        }

        $base = $a->rendered_html ?: ($a->template->html ?? '');
        $filled = $render->renderHtmlWithPayload($base, $a->payload_json ?? []);

        // Embed de firma como data URI (evita problemas de recursos remotos en DomPDF)
        $sigBin = Storage::disk('local')->get($a->signature_path);
        $sigData = 'data:image/png;base64,' . base64_encode($sigBin);
        $finalHtml = $render->injectSignature($filled, $sigData);

        // Generar PDF
        $pdf = Pdf::loadHTML($this->wrapHtml($finalHtml));
        // (opcional) $pdf->setOptions(['isRemoteEnabled' => true]);
        $pdf->setPaper('a4');

        $dir = 'contracts/' . date('Ymd');
        $name = $a->id . '-' . Str::random(6) . '.pdf';
        $path = $dir . '/' . $name;

        Storage::disk('local')->put($path, $pdf->output());

        // Actualiza DB usando paths locales (y limpiando campos cloud si existían)
        $a->update([
            'pdf_path' => $path,
            'pdf_url' => null,        // ya no dependemos de URL externa
            'pdf_public_id' => null,        // ya no usamos Cloudinary
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        return $a->fresh();
    }

    private function wrapHtml(string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,Arial,sans-serif; font-size:12px; line-height:1.4} img{max-width:100%}</style></head><body>' . $body . '</body></html>';
    }

    private function uploadToCloudinary(string $path, string $publicId): string
    {
        // Cloudinary PHP v2
        $uploader = new \Cloudinary\Api\Upload\UploadApi();
        $res = $uploader->upload($path, [
            'public_id' => $publicId,
            'folder' => '', // opcional, ya usamos public_id con ruta
            'resource_type' => 'auto',   // << CLAVE
        ]);
        return $res['secure_url'] ?? $res['url'];
    }

    private function authorizeView(Request $request, ContractAssignment $a): void
    {
        $user = $request->user();
        if ($user && property_exists($user, 'account_id')) {
            abort_unless($a->account_id == $user->account_id, 403);
        }
        // si es paciente, debe ser suya
        if ($user && method_exists($user, 'isPatient') && $user->isPatient()) {
            abort_unless($a->patient_id == $user->id, 403);
        }
    }



    public function downloadPdf(Request $request, $id)
    {
        $a = ContractAssignment::findOrFail($id);
        $this->authorizeView($request, $a);

        if (!$a->pdf_path || !Storage::disk('local')->exists($a->pdf_path)) {
            return response()->json(['message' => 'PDF no disponible'], 404);
        }

        $filename = 'contrato-' . $a->id . '.pdf';
        $fullPath = Storage::disk('local')->path($a->pdf_path);

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
