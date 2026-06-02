@extends('email.layouts.base')

@section('content')
    <h1 style="font-size:22px; color:#0f172a; margin-bottom:20px; font-weight:700;">
        ⚠️ Hay un problema con tu campaña
    </h1>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        Hola {{ $user->name }},
    </p>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        Desafortunadamente, no pudimos procesar el pago de tu campaña <strong>{{ $package->name }}</strong>.
        {{ $errorMessage ? 'Detalles: ' . $errorMessage : 'Esto suele ocurrir cuando hay un problema con tu método de pago.' }}
    </p>

    <!-- Lo que necesitas hacer -->
    <div style="background:#fef2f2; border-left:4px solid #dc2626; padding:20px; margin:20px 0; border-radius:4px;">
        <h3 style="font-size:14px; color:#0f172a; margin:0 0 15px 0; font-weight:600;">
            ¿Qué puedo hacer?
        </h3>

        <ul style="margin:0; padding-left:20px; color:#334155; font-size:13px; line-height:2;">
            <li>Revisa que tu método de pago sea válido y no esté vencido</li>
            <li>Intenta pagar nuevamente desde tu dashboard</li>
            <li>Contacta a tu banco si el problema persiste</li>
            <li>Si necesitas ayuda, nuestro equipo está disponible</li>
        </ul>
    </div>

    <!-- CTA -->
    <table cellpadding="0" cellspacing="0" style="margin-top:30px;">
        <tr>
            <td>
                <a href="{{ $retryUrl }}"
                    style="
                display:inline-block;
                padding:12px 24px;
                background:#0077b6;
                color:#ffffff;
                text-decoration:none;
                border-radius:6px;
                font-size:14px;
                font-weight:600;">
                    Reintentar pago →
                </a>
            </td>
        </tr>
    </table>

    <!-- Info -->
    <p style="font-size:13px; color:#64748b; margin-top:30px; line-height:1.6;">
        Tu información de la campaña está guardada, así que cuando resuelvas el problema de pago,
        podrás activar tu campaña sin necesidad de ingresarla de nuevo.
    </p>

    <p style="font-size:14px; color:#334155; margin-top:20px;">
        Estamos aquí para ayudarte.
    </p>

    <p style="font-size:14px; color:#334155; margin-top:15px;">
        <strong>Equipo MindMeet</strong>
    </p>
@endsection
