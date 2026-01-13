@extends('email.layouts.base')

@section('content')
    <h1 style="
        font-size:22px;
        color:#000000;
        margin-bottom:20px;
        font-weight:600;
    ">
        ✅ Identidad Verificada
    </h1>

    <p style="font-size:15px; color:#333333; margin-bottom:15px;">
        <strong>¡Tu identidad ha sido verificada correctamente!</strong>
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Nos complace informarte que tu identidad ha sido verificada exitosamente por nuestro equipo de revisión.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Tu cuenta ahora está completamente activa y sera visible dentro del catalogo de profesionales verificados de
        mindmeet.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:30px;">
        <strong>Equipo MindMeet</strong>
    </p>
@endsection
