<?php

namespace App\Http\Controllers;

use App\Models\IdentityValidations;
use App\Http\Controllers\Controller;
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
        if ($request->type === 'cedula') {
            $user->cedula_selfie_url = $request->url;
        } elseif ($request->type === 'ine') {
            $user->ine_selfie_url = $request->url;
        }
        if ($user->cedula_selfie_url && $user->ine_selfie_url) {
            $user->identity_verification_status = 'sending';
        }
        $user->save();
        return response()->json($user, 200);
    }
}