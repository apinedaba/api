<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;

class DeviceTokenController extends Controller
{
  public function store(Request $r)
  {
    $r->validate(['platform' => 'required|string|max:20', 'token' => 'required|string']);
    DeviceToken::updateOrCreate(
      ['token' => $r->token],
      [
        'user_id' => $r->user() instanceof User ? $r->user()->id : null,
        'platform' => $r->platform,
        'notifiable_type' => $r->user()::class,
        'notifiable_id' => $r->user()->id,
      ]
    );
    return response()->json(['ok' => true]);
  }

  public function register(Request $request)
  {
    $request->validate([
      'user_id' => 'required',
      'token' => 'required'
    ]);

    $user = User::find($request->user_id);
    $user->push_token = $request->token;
    $user->save();

    return response()->json(['success' => true]);
  }

  public function sendPush(User $user, $title, $body)
  {
    $response = Http::withHeaders([
      'Authorization' => 'key=' . config('services.fcm.server_key'),
      'Content-Type' => 'application/json',
    ])->post('https://fcm.googleapis.com/fcm/send', [
          'to' => $user->push_token,
          'notification' => [
            'title' => $title,
            'body' => $body,
            'icon' => 'https://mindmeet.com.mx/assets/icon.png',
          ],
        ]);

    return $response->json();
  }
}
