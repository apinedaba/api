<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $profile = User::where("id", $user->id)->first();
        return response()->json($profile, 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $profile = User::where("id", $user->id);
        $count = $profile->count();
        $data = $request->all();    
        
        if (isset($data["password"]) ) {
            $data["password"] = Hash::make($request->password);
        }   
        if ($count > 0) {
            $profile->update($data);
            $profile = User::where("id", $user->id)->first();
            return response()->json($profile, 200);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $profile)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $profile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $profile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $profile)
    {
        //
    }
}
