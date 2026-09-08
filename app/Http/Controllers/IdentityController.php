<?php

namespace App\Http\Controllers;

use App\Models\IdentityValidations;
use App\Http\Controllers\Controller;
use App\Support\IdentityVerificationStatus;
use Illuminate\Http\Request;

class IdentityController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:cedula,ine',
            'url' => 'required|string',
        ]);

        $user = auth()->user();
        if ($user->identity_verification_status === IdentityVerificationStatus::APPROVED) {
            return response()->json([
                'message' => 'La identidad ya fue aprobada y sus documentos no se pueden reemplazar.',
            ], 422);
        }

        if ($request->type === 'cedula') {
            $user->cedula_selfie_url = $request->url;
        } elseif ($request->type === 'ine') {
            $user->ine_selfie_url = $request->url;
        }
        if ($user->cedula_selfie_url && $user->ine_selfie_url) {
            $user->identity_verification_status = IdentityVerificationStatus::SENDING;
        }
        $user->save();
        return response()->json($user, 200);
    }
}
