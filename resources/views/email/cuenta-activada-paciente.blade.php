@extends('email.layouts.base')

@section('content')

    <h1 style="
                        font-size:22px;
                        color:#000000;
                        margin-bottom:20px;
                        font-weight:600;
                    ">
        Tu cuenta ya está activa 🎉
    </h1>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Nos alegra informarte que <strong>tu cuenta en MindMeet ya se encuentra activa</strong>.
        A partir de este momento, tú y tu psicólogo pueden comenzar a utilizar
        las herramientas de la plataforma para acompañar tu proceso terapéutico.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Desde MindMeet podrás:
    </p>

    <ul style="font-size:14px; color:#444444; line-height:1.6; padding-left:18px;">
        <li>📅 Consultar y gestionar tus citas</li>
        <li>💬 Mantener comunicación con tu psicólogo</li>
        <li>📄 Acceder a recursos y herramientas de apoyo</li>
        <li>🔒 Gestionar tu información de forma segura</li>
    </ul>

    <p style="font-size:14px; color:#444444; line-height:1.6; margin-top:15px;">
        Puedes iniciar sesión en cualquier momento desde el siguiente enlace:
    </p>

    <!-- CTA -->
    <table cellpadding="0" cellspacing="0" style="margin-top:25px;">
        <tr>
            <td>
                <a href="{{ $url ?? '#' }}" style="
                                   display:inline-block;
                                   padding:12px 24px;
                                   background:#0077b6;
                                   color:#ffffff;
                                   text-decoration:none;
                                   border-radius:6px;
                                   font-size:14px;
                                   font-weight:600;">
                    Iniciar sesión en MindMeet
                </a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px; color:#555555; margin-top:30px;">
        Si tienes alguna duda o necesitas apoyo, recuerda que siempre puedes
        comunicarte con tu psicólogo o con nuestro equipo.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:25px;">
        Te damos la bienvenida a <strong>MindMeet</strong><br>
        Estamos contigo en este proceso 🤍
    </p>

@endsection