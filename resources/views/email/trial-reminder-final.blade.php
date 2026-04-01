@extends('email.layouts.base')

@section('content')

    <h1 style="
            font-size:22px;
            color:#b91c1c;
            margin-bottom:20px;
            font-weight:600;
        ">
        Último aviso
    </h1>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Han pasado varios días desde que tu periodo de prueba en
        <strong>MindMeet</strong> terminó y no se ha activado ningún plan.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        <strong>Tu cuenta ha sido deshabilitada</strong> debido a la falta de
        un plan activo.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Aún puedes reactivar tu acceso contratando alguno de nuestros planes:
    </p>

    <!-- PLANES -->
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">
        <tr>
            <td style="padding:14px; border:1px solid #e5e7eb; border-radius:8px;">
                <strong>💳 Plan mensual</strong><br>
                $99 MXN al mes
            </td>
        </tr>
        <tr>
            <td height="10"></td>
        </tr>
        <tr>
            <td style="padding:14px; border:1px solid #e5e7eb; border-radius:8px;">
                <strong>⭐ Plan anual</strong><br>
                $1,089 MXN al año · Acceso continuo
            </td>
        </tr>
    </table>

    <!-- CTA -->
    <table cellpadding="0" cellspacing="0" style="margin-top:30px;">
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
                    Reactivar mi cuenta
                </a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px; color:#555555; margin-top:30px;">
        Al activar un plan, tu acceso será restaurado automáticamente.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:25px;">
        <strong>MindMeet</strong>
    </p>

@endsection