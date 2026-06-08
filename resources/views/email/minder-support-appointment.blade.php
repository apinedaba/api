@extends('email.layouts.base')

@section('content')
    <h1 style="margin:0 0 14px; color:#111827; font-size:24px; line-height:1.25;">
        {{ $isInternal ? 'Solicitud de apoyo MindMeet' : 'Hola ' . ($notifiableName ?: '') }}
    </h1>

    <p style="margin:0 0 18px; color:#374151; font-size:15px; line-height:1.6;">
        {{ $intro }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0; background:#f8fbff; border:1px solid #dbeafe; border-radius:14px;">
        <tr>
            <td style="padding:18px;">
                <p style="margin:0 0 10px; color:#0f172a; font-size:16px; font-weight:700;">
                    {{ $topicLabel }}
                </p>
                <p style="margin:0 0 6px; color:#475569; font-size:14px;">
                    <strong>Fecha propuesta:</strong> {{ $date->translatedFormat('d \\d\\e F \\d\\e Y') }}
                </p>
                <p style="margin:0; color:#475569; font-size:14px;">
                    <strong>Hora:</strong> {{ $date->format('H:i') }} · <strong>Duración:</strong> {{ $appointment->duration_minutes }} minutos
                </p>
            </td>
        </tr>
    </table>

    @if($isInternal)
        <table width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0; background:#ffffff; border:1px solid #e5e7eb; border-radius:14px;">
            <tr>
                <td style="padding:18px;">
                    <p style="margin:0 0 8px; color:#111827; font-size:15px;"><strong>Psicólogo:</strong> {{ $psychologistName }}</p>
                    <p style="margin:0 0 14px; color:#111827; font-size:15px;"><strong>Correo:</strong> {{ $psychologistEmail }}</p>
                    <p style="margin:0 0 8px; color:#111827; font-size:15px; font-weight:700;">Descripción de la solicitud</p>
                    <p style="margin:0; color:#475569; font-size:14px; line-height:1.6;">{{ $appointment->description }}</p>
                </td>
            </tr>
        </table>

        <p style="margin:24px 0; text-align:center;">
            <a href="{{ $supportUrl }}" style="display:inline-block; background:#007ca9; color:#ffffff; text-decoration:none; padding:12px 20px; border-radius:8px; font-weight:700; font-size:14px;">
                Revisar solicitud en superadmin
            </a>
        </p>
    @else
        @if($event === 'requested')
            <p style="margin:0 0 18px; color:#374151; font-size:15px; line-height:1.6;">
                Tu solicitud quedó como pendiente. Te avisaremos cuando el horario sea confirmado o ajustado por el equipo MindMeet.
            </p>
        @elseif($appointment->meeting_url)
            <p style="margin:24px 0; text-align:center;">
                <a href="{{ $appointment->meeting_url }}" style="display:inline-block; background:#007ca9; color:#ffffff; text-decoration:none; padding:12px 20px; border-radius:8px; font-weight:700; font-size:14px;">
                    Abrir videollamada
                </a>
            </p>
        @else
            <p style="margin:0 0 18px; color:#374151; font-size:15px; line-height:1.6;">
                El equipo MindMeet agregará el enlace de videollamada antes de la sesión.
            </p>
        @endif
    @endif

    <p style="margin:24px 0 0; color:#111827; font-size:15px; line-height:1.6;">
        Con emoción,<br>
        El equipo de <strong>MindMeet</strong>
    </p>
@endsection
