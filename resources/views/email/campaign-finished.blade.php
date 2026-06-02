@extends('email.layouts.base')

@section('content')

    <h1 style="font-size:22px; color:#0f172a; margin-bottom:20px; font-weight:700;">
        Tu campaña MindBoost finalizó
    </h1>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        Hola {{ $user->name }},
    </p>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        La campaña <strong>{{ $package->name }}</strong> ha finalizado.
        Ya puedes contratar una nueva campaña o revisar el historial desde tu panel.
    </p>

    <div style="background:#f8fafc; border-left:4px solid #64748b; padding:20px; margin:20px 0; border-radius:4px;">
        <table style="width:100%; font-size:13px; color:#334155;">
            <tr>
                <td style="padding:8px 0; font-weight:600;">Paquete:</td>
                <td style="padding:8px 0;">{{ $package->name }}</td>
            </tr>
            @if ($campaignRequest->starts_at)
                <tr>
                    <td style="padding:8px 0; font-weight:600;">Inicio:</td>
                    <td style="padding:8px 0;">{{ $campaignRequest->starts_at->format('d/m/Y') }}</td>
                </tr>
            @endif
            @if ($campaignRequest->ends_at)
                <tr>
                    <td style="padding:8px 0; font-weight:600;">Finalizó:</td>
                    <td style="padding:8px 0;">{{ $campaignRequest->ends_at->format('d/m/Y') }}</td>
                </tr>
            @endif
        </table>
    </div>

    <table cellpadding="0" cellspacing="0" style="margin-top:30px;">
        <tr>
            <td>
                <a href="{{ $dashboardUrl }}"
                    style="display:inline-block; padding:12px 24px; background:#0077b6; color:#ffffff; text-decoration:none; border-radius:6px; font-size:14px; font-weight:600;">
                    Ver campañas
                </a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px; color:#64748b; margin-top:30px; line-height:1.6;">
        Si quieres volver a impulsar tu perfil, puedes contratar otra campaña desde MindBoost.
    </p>

    <p style="font-size:14px; color:#334155; margin-top:15px;">
        <strong>Equipo MindMeet</strong>
    </p>

@endsection
