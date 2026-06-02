@extends('email.layouts.base')

@section('content')

    <h1 style="font-size:22px; color:#0f172a; margin-bottom:20px; font-weight:700;">
        ✅ Recibimos tu pago de campaña
    </h1>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        Hola {{ $user->name }},
    </p>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        ¡Excelente noticia! Tu pago ha sido procesado correctamente para la campaña
        <strong>{{ $package->name }}</strong>. El equipo MindMeet trabajará tu campaña y te notificará cuando comience a circular.
    </p>

    <!-- Resumen de la campaña -->
    <div style="background:#f8fafc; border-left:4px solid #0077b6; padding:20px; margin:20px 0; border-radius:4px;">
        <h3 style="font-size:14px; color:#0f172a; margin:0 0 15px 0; font-weight:600;">
            📊 Resumen de tu campaña
        </h3>

        <table style="width:100%; font-size:13px; color:#334155;">
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:8px 0; font-weight:600;">Paquete:</td>
                <td style="padding:8px 0;">{{ $package->name }}</td>
            </tr>
            @if ($targetAudience['specialty_focus'] ?? null)
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:8px 0; font-weight:600;">Especialidad enfocada:</td>
                    <td style="padding:8px 0;">{{ $targetAudience['specialty_focus'] }}</td>
                </tr>
            @endif
            @if ($targetAudience['age_range'] ?? null)
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:8px 0; font-weight:600;">Rango de edad:</td>
                    <td style="padding:8px 0;">{{ $targetAudience['age_range'] }} años</td>
                </tr>
            @endif
            @if ($targetAudience['gender'] ?? null)
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:8px 0; font-weight:600;">Género objetivo:</td>
                    <td style="padding:8px 0;">
                        @if ($targetAudience['gender'] === 'todos')
                            Todos
                        @elseif($targetAudience['gender'] === 'femenino')
                            Femenino
                        @elseif($targetAudience['gender'] === 'masculino')
                            Masculino
                        @endif
                    </td>
                </tr>
            @endif
            @if ($locations && count($locations) > 0)
                <tr>
                    <td style="padding:8px 0; font-weight:600;">Ciudades:</td>
                    <td style="padding:8px 0;">{{ implode(', ', $locations) }}</td>
                </tr>
            @endif
        </table>
    </div>

    <!-- Lo que pasa ahora -->
    <div style="background:#f0fdf4; border:1px solid #dbeafe; padding:20px; margin:20px 0; border-radius:6px;">
        <h3 style="font-size:14px; color:#0f172a; margin:0 0 12px 0; font-weight:600;">
            ¿Qué pasa ahora?
        </h3>

        <ul style="margin:0; padding-left:20px; color:#334155; font-size:13px; line-height:2;">
            <li>El equipo MindMeet revisará y preparará tu campaña</li>
            <li>Si contrataste CombiMindMeet, se publicará cuando se llenen todos los espacios</li>
            <li>Te enviaremos otro correo cuando tu campaña sea activada</li>
            <li>También podrás ver el estatus desde tu dashboard</li>
        </ul>
    </div>

    <!-- CTA -->
    <table cellpadding="0" cellspacing="0" style="margin-top:30px;">
        <tr>
            <td>
                <a href="{{ $dashboardUrl }}"
                    style="
                display:inline-block;
                padding:12px 24px;
                background:#0077b6;
                color:#ffffff;
                text-decoration:none;
                border-radius:6px;
                font-size:14px;
                font-weight:600;">
                    Ver estatus en mi dashboard →
                </a>
            </td>
        </tr>
    </table>

    <!-- Soporte -->
    <p style="font-size:13px; color:#64748b; margin-top:30px; line-height:1.6;">
        Si tienes preguntas sobre tu campaña, el equipo MindMeet puede ayudarte a ajustar el brief antes de publicarla.
    </p>

    <p style="font-size:14px; color:#334155; margin-top:20px;">
        <strong>¡Mucho éxito en tu campaña!</strong>
    </p>

    <p style="font-size:14px; color:#334155; margin-top:15px;">
        <strong>Equipo MindMeet</strong>
    </p>

@endsection
