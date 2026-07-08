<?php

namespace App\Http\Controllers;

use App\Services\ProfessionalReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionalReferralController extends Controller
{
    public function summary(Request $request, ProfessionalReferralService $referralService): JsonResponse
    {
        return response()->json($referralService->summaryFor(
            $request->user(),
            $this->resolvePsychologistFrontendUrl()
        ));
    }

    private function resolvePsychologistFrontendUrl(): string
    {
        $candidates = [
            config('app.front_url_psicologo'),
            app()->environment('local') ? 'http://localhost:3001' : null,
            config('app.front_url_user'),
            config('app.front_url'),
            config('app.frontend_url'),
            'https://minder.mindmeet.com.mx',
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && preg_match('/^https?:\/\//', $candidate)) {
                return rtrim($candidate, '/');
            }
        }

        return 'https://minder.mindmeet.com.mx';
    }
}
