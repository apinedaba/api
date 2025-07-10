<?php

namespace App\Notifications\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

trait NotificaInternamente
{
    /**
     * Envía una notificación por correo a la lista de personal interno.
     *
     * @param \App\Models\User|\App\Models\Patient $usuario
     * @param string $asunto
     * @param string $cuerpoMensaje
     */
    protected function enviarNotificacionInterna($usuario, $asunto, $cuerpoMensaje)
    {
        // Solo enviar en producción para no generar spam en desarrollo
        if (env('APP_ENV') !== 'production') {
            return;
        }
        $correosInternos = ['jhernandez961116@gmail.com', 'apinedabawork@gmail.com', 'axelboyzowork@gmail.com'];

        foreach ($correosInternos as $correo) {
            Mail::raw($cuerpoMensaje, function ($message) use ($correo, $asunto) {
                $message->to($correo)
                    ->subject($asunto);
            });
        }
    }
}
