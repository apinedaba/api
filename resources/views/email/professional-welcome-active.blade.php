@extends('email.layouts.base')

@section('content')
    <h1 style="font-size:24px; color:#0f172a; margin:0 0 16px; font-weight:700;">
        Bienvenido(a) a MindMeet
    </h1>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 14px;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 14px;">
        Gracias por registrarte en MindMeet. Tu cuenta profesional ya esta activa y lista para usar.
    </p>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 24px;">
        Ya puedes entrar a tu panel para completar tu perfil, configurar tu agenda y comenzar a gestionar tus pacientes.
    </p>

    <a href="{{ $dashboardUrl }}" style="display:inline-block; padding:12px 22px; background:#0077b6; color:#ffffff; text-decoration:none; border-radius:6px; font-size:14px; font-weight:600;">
        Ir a mi panel
    </a>
@endsection
