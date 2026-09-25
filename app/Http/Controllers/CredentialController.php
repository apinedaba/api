<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use App\Models\Administrator;
use App\Services\CredentialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CredentialController extends Controller
{
    public function psychologist(Request $request, CredentialService $credentials)
    {
        $credential = $credentials->forPsychologist($request->user());
        $credential['qrSvg'] = $credential['publicProfileUrl']
            ? $credentials->qrSvgDataUri($credential['publicProfileUrl'])
            : null;

        return response()->json($credential);
    }

    public function patient(Request $request, CredentialService $credentials)
    {
        return response()->json($credentials->forPatient($request->user()));
    }

    public function psychologistPdf(Request $request, CredentialService $credentials)
    {
        $credential = $credentials->forPsychologist($request->user());
        $credential['qrSvg'] = $credential['publicProfileUrl'] ? $credentials->qrSvgDataUri($credential['publicProfileUrl']) : null;

        return $this->pdf($credential, 'credencial-mindmeet-psicologo.pdf');
    }

    public function patientPdf(Request $request, CredentialService $credentials)
    {
        return $this->pdf($credentials->forPatient($request->user()), 'credencial-mindmeet-paciente.pdf');
    }

    public function administrator(Request $request, CredentialService $credentials)
    {
        return response()->json($credentials->forAdministrator($this->administratorFromRequest($request)));
    }

    public function administratorPdf(Request $request, CredentialService $credentials)
    {
        return $this->pdf(
            $credentials->forAdministrator($this->administratorFromRequest($request)),
            'credencial-mindmeet-superadmin.pdf'
        );
    }

    public function administratorPhoto(Request $request, CredentialService $credentials)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $administrator = $this->administratorFromRequest($request);

        try {
            $result = (new UploadApi())->upload($request->file('photo')->getRealPath(), [
                'folder' => 'mindmeet/administrators',
                'public_id' => "superadmin-{$administrator->id}",
                'overwrite' => true,
                'resource_type' => 'image',
                'transformation' => [
                    ['width' => 800, 'height' => 800, 'crop' => 'fill', 'gravity' => 'face'],
                ],
            ]);

            if (empty($result['secure_url'])) {
                throw new \RuntimeException('Cloudinary no devolvió una URL segura.');
            }

            $administrator->forceFill(['image' => $result['secure_url']])->save();

            return response()->json($credentials->forAdministrator($administrator->fresh()));
        } catch (\Throwable $exception) {
            Log::error('Error al subir la foto de credencial del superadmin a Cloudinary.', [
                'administrator_id' => $administrator->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'No se pudo subir la foto a Cloudinary.'], 500);
        }
    }

    private function administratorFromRequest(Request $request): Administrator
    {
        $administrator = $request->user('web');

        abort_unless($administrator instanceof Administrator, 403, 'No autorizado.');

        return $administrator;
    }

    private function pdf(array $credential, string $filename)
    {
        // CR80 vertical: 53.98 × 85.60 mm, expressed in DomPDF points.
        return Pdf::loadView('pdf.credential', compact('credential'))
            ->setPaper([0, 0, 153.01, 242.65])
            ->download($filename);
    }
}
