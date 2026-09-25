<?php

namespace App\Http\Controllers;

use App\Services\ProfessionalReferralService;
use Illuminate\Http\Request;

class ProfessionalReferralController extends Controller
{
    public function summary(Request $request, ProfessionalReferralService $service)
    {
        return response()->json($service->summary($request->user()));
    }
}
