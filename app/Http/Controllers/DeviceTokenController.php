<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\DeviceToken;

class DeviceTokenController extends Controller {
  public function store(Request $r) {
    $r->validate(['platform'=>'required|in:web', 'token'=>'required|string']);
    DeviceToken::updateOrCreate(
      ['token'=>$r->token],
      ['user_id'=>$r->user()->id, 'platform'=>$r->platform]
    );
    return response()->json(['ok'=>true]);
  }
}