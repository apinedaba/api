<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\DeviceToken;
use App\Services\Fcm;

class TestPushController extends Controller
{
  public function send(Request $r)
  {
    $user = $r->user(); // psicólogo autenticado
    $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->all();
    foreach ($tokens as $token) {
      Fcm::send($token, "Notificacion de prueba", "¡Push funcionando!", ['link' => 'https://mindmeet.com.mx' ]);
    }
    return ['ok' => true, 'sent_to' => count($tokens)];
  }
}