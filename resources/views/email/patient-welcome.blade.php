@extends('email.layouts.base')

@section('content')
    <h1 style="font-size:24px; color:#0f172a; margin:0 0 16px; font-weight:700;">
        Bienvenido(a) a MindMeet
    </h1>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 14px;">
        Hola {{ $patientName ?? '' }},
    </p>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 14px;">
        Gracias por registrarte en nuestra plataforma. Tu cuenta ya esta lista para acompanarte en tu proceso de bienestar.
    </p>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 24px;">
        Desde tu panel puedes revisar tus sesiones, gestionar tu informacion y mantenerte cerca de tu profesional.
    </p>

    <a href="{{ $dashboardUrl }}" style="display:inline-block; padding:12px 22px; background:#0077b6; color:#ffffff; text-decoration:none; border-radius:6px; font-size:14px; font-weight:600;">
        Ir a mi cuenta
    </a>

    <p style="font-size:13px; color:#64748b; line-height:1.7; margin:26px 0 0;">
        Si no reconoces este registro, puedes ignorar este mensaje.
    </p>
@endsection
