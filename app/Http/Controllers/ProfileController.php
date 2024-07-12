<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $profile = Profile::where("user_id", $user->id)->first();
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
        $profile = Profile::where("user_id", $user->id);
        $count = $profile->count();
        $data = $request->all();
        $data['user_id'] = $user->id;
        if ($count > 0) {
            $profile->update([
                "publicName"=>$data["publicName"]?:"",
                "movil"=>$data["movil"]?:"",
                "office"=>$data["office"]?:0,
                "whatsapp"=>$data["whatsapp"]?:"",               
                "user_id"=>$data["user_id"]?:""
            ]);
            $profile = Profile::where("user_id", $user->id)->first();
            return response()->json($profile, 200);

        }else {
            $profile = Profile::create($data);
            return response()->json($profile, 200);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Profile $profile)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Profile $profile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Profile $profile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Profile $profile)
    {
        //
    }
}
