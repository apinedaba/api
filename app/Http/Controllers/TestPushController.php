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
      Fcm::send($token, "Ahuevo perros", "¡Ya tenemos notificaciones push, luego me aplauden no se preocupen!", ['link' => 'https://mindmeet.com.mx', 'icon' => 'https://mindmeet.com.mx/favicon/android-chrome-192x192.png']);
    }
    return ['ok' => true, 'sent_to' => count($tokens)];
  }

  public function DownloadappNotification(Request $r)
  {
    $tokens = DeviceToken::pluck('token')->all();
    foreach ($tokens as $token) {
      Fcm::send($token, "Descarga la app", "¡Ahora mindmeet esta en android para usuarios android! Descargala para acceder mas rapido a tu cuenta", ['link' => 'https://play.google.com/store/apps/details?id=mx.com.mindmeet.minder.twa', 'icon' => 'https://res.cloudinary.com/dabwvv94x/image/upload/v1764639595/android-chrome-192x192_aogrgh.png']);
    }
    return ['ok' => true, 'sent_to' => count($tokens)];
  }
}