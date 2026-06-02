@extends('email.layouts.base')

@section('content')

    <h1 style="font-size:22px; color:#0f172a; margin-bottom:20px; font-weight:700;">
        🚀 Tu campaña MindBoost ya está activa
    </h1>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        Hola {{ $user->name }},
    </p>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        Tu campaña <strong>{{ $package->name }}</strong> ya comenzó a circular.
        Puedes revisar su estatus desde tu panel de MindMeet.
    </p>

    <div style="background:#f8fafc; border-left:4px solid #0077b6; padding:20px; margin:20px 0; border-radius:4px;">
        <h3 style="font-size:14px; color:#0f172a; margin:0 0 15px 0; font-weight:600;">
            Resumen
        </h3>

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
                    <td style="padding:8px 0; font-weight:600;">Termina:</td>
                    <td style="padding:8px 0;">{{ $campaignRequest->ends_at->format('d/m/Y') }}</td>
                </tr>
            @endif
            @if ($campaignRequest->campaign_url)
                <tr>
                    <td style="padding:8px 0; font-weight:600;">Link:</td>
                    <td style="padding:8px 0;">
                        <a href="{{ $campaignRequest->campaign_url }}" style="color:#0077b6;">
                            Ver campaña publicada
                        </a>
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <table cellpadding="0" cellspacing="0" style="margin-top:30px;">
        <tr>
            <td>
                <a href="{{ $dashboardUrl }}"
                    style="display:inline-block; padding:12px 24px; background:#0077b6; color:#ffffff; text-decoration:none; border-radius:6px; font-size:14px; font-weight:600;">
                    Ver estatus de mi campaña
                </a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px; color:#64748b; margin-top:30px; line-height:1.6;">
        Si tienes dudas sobre tu campaña, responde este correo o contacta al equipo MindMeet.
    </p>

    <p style="font-size:14px; color:#334155; margin-top:15px;">
        <strong>Equipo MindMeet</strong>
    </p>

@endsection
