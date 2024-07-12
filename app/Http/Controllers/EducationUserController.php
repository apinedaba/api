<?php

namespace App\Http\Controllers;

use App\Models\EducationUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
class EducationUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $education = EducationUser::where('user_id', $user->id);
        $count = $education->count();

        if ($count > 0) {            
            $obj = $education->first();
            $schools = json_decode($obj->schools);
            return response()->json( $schools, 200);
        }    
        return response()->json([], 200);
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
        $education = EducationUser::where('user_id', $user->id);
        $count = $education->count();

        if ($count > 0) {
            $obj = $education->first();
            $schools = json_decode($obj->schools);
            array_push($schools, $request->all());
            $education = $education->update([
                'schools' => json_encode($schools),                
            ]);
            return response()->json( $schools, 200);

        }else {
            $obj = EducationUser::create([
                'schools' => json_encode([$request->all()]),
                'user_id' => $user->id
            ]);
            $schools = json_decode($obj->schools);
            return response()->json( $schools, 200);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(EducationUser $educationUser)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EducationUser $educationUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EducationUser $educationUser)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EducationUser $educationUser)
    {
        //
    }
}
