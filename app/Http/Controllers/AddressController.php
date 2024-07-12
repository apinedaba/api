<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $address = Address::where("user_id", $user->id)->first();
        return response()->json($address, 200);
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
        $address = Address::where("user_id", $user->id);
        $count = $address->count();
        $data = $request->all();
        $data['user_id'] = $user->id;
        if ($count > 0) {
            $address->update([
                "street"=>$data["street"]?:"",
                "zipcode"=>$data["zipcode"]?:"",
                "city"=>$data["city"]?:"",
                "neighborhood"=>$data["neighborhood"]?:"",
                "country"=>$data["country"]?:"",
                "state"=>$data["state"]?:"",
                "user_id"=>$data["user_id"]?:""
            ]);
            $address = Address::where("user_id", $user->id)->first();
            return response()->json($address, 200);

        }else {
            $address = Address::create($data);
            return response()->json($address, 200);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Address $address)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Address $address)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Address $address)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Address $address)
    {
        //
    }
}
