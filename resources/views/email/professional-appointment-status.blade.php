@extends('email.layouts.base')

@section('content')
    <h1 style="font-size:24px; color:#0f172a; margin:0 0 16px; font-weight:700;">
        Actualizacion de sesion
    </h1>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 14px;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 18px;">
        {{ $patientName }} actualizo el estado de la sesion.
    </p>

    <table cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin:0 0 24px;">
        <tr>
            <td style="padding:18px;">
                <p style="font-size:14px; color:#334155; margin:0 0 8px;"><strong>Nuevo estado:</strong> {{ $status }}</p>
                <p style="font-size:14px; color:#334155; margin:0 0 8px;"><strong>Fecha:</strong> {{ $date }}</p>
                <p style="font-size:14px; color:#334155; margin:0;"><strong>Hora:</strong> {{ $time }}</p>
            </td>
        </tr>
    </table>

    <a href="{{ $agendaUrl }}" style="display:inline-block; padding:12px 22px; background:#0077b6; color:#ffffff; text-decoration:none; border-radius:6px; font-size:14px; font-weight:600;">
        Ver agenda
    </a>
@endsection
