<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HelpCenterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [],
        ]);
    }
}
