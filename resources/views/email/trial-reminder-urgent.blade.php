@extends('email.layouts.base')

@section('content')

    <h1 style="
                font-size:22px;
                color:#000000;
                margin-bottom:20px;
                font-weight:600;
            ">
        Evita la deshabilitación de tu cuenta
    </h1>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Tu periodo de prueba en <strong>MindMeet</strong> ya terminó y tu cuenta
        se encuentra en riesgo de ser deshabilitada.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Para seguir utilizando la plataforma sin interrupciones, es necesario
        activar un plan.
    </p>

    <!-- PLANES -->
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">
        <tr>
            <td style="padding:14px; border:1px solid #e5e7eb; border-radius:8px;">
                <strong>💳 Plan mensual</strong><br>
                $10 MXN al mes
            </td>
        </tr>
        <tr>
            <td height="10"></td>
        </tr>
        <tr>
            <td style="padding:14px; border:1px solid #e5e7eb; border-radius:8px;">
                <strong>⭐ Plan anual</strong><br>
                $100 MXN al año · Evita renovaciones
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
                           background:#f59e0b;
                           color:#ffffff;
                           text-decoration:none;
                           border-radius:6px;
                           font-size:14px;
                           font-weight:600;">
                    Activar mi plan ahora
                </a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px; color:#555555; margin-top:30px;">
        Si no activas un plan, tu cuenta será deshabilitada automáticamente.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:25px;">
        <strong>MindMeet</strong>
    </p>

@endsection