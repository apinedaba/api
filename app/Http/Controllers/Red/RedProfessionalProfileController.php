<?php

namespace App\Http\Controllers\Red;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RedReputationService;
use Illuminate\Http\JsonResponse;

class RedProfessionalProfileController extends Controller
{
    public function show(User $user, RedReputationService $reputation): JsonResponse
    {
        abort_unless($user->activo && $user->identity_verification_status === 'approved', 404);

        return response()->json(['data' => $reputation->profile($user)]);
    }
}
