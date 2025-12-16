@extends('email.layouts.base')

@section('content')

    <h1 style="
                font-size:22px;
                color:#000000;
                margin-bottom:20px;
                font-weight:600;
            ">
        Tu periodo de prueba ha terminado
    </h1>

    <p style="font-size:15px; color:#333333; margin-bottom:15px;">
        <strong>Tu cuenta será deshabilitada</strong>
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Tu periodo de prueba en <strong>MindMeet</strong> ha llegado a su fin.
        Para continuar utilizando la plataforma y evitar la deshabilitación
        de tu cuenta, es necesario adquirir un plan activo.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Elige la opción que mejor se adapte a ti:
    </p>

    <!-- PLANES -->
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">
        <tr>
            <td style="padding:15px; border:1px solid #e5e7eb; border-radius:8px;">
                <p style="margin:0; font-size:15px; font-weight:600;">
                    💳 Plan Mensual
                </p>
                <p style="margin:8px 0; font-size:14px;">
                    <strong>$10 MXN</strong> al mes
                </p>
            </td>
        </tr>
        <tr>
            <td height="10"></td>
        </tr>
        <tr>
            <td style="padding:15px; border:1px solid #e5e7eb; border-radius:8px;">
                <p style="margin:0; font-size:15px; font-weight:600;">
                    ⭐ Plan Anual (recomendado)
                </p>
                <p style="margin:8px 0; font-size:14px;">
                    <strong>$100 MXN</strong> al año<br>
                    <span style="font-size:12px; color:#16a34a;">
                        Ahorra y evita problemas de renovación
                    </span>
                </p>
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
                    Activar mi plan
                </a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px; color:#555555; margin-top:30px;">
        Si no se activa un plan, tu cuenta será deshabilitada automáticamente.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:25px;">
        <strong>MindMeet</strong>
    </p>

@endsection