@extends('email.layouts.base')

@section('content')
    <h1 style="
        font-size:22px;
        color:#000000;
        margin-bottom:20px;
        font-weight:600;
    ">
        ⚠️ Verificación de Identidad Rechazada
    </h1>

    <p style="font-size:15px; color:#333333; margin-bottom:15px;">
        <strong>Necesitamos que actualices {{ $documentType ?? 'tus documentos de identidad' }}</strong>
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Te informamos que no pudimos aprobar {{ $documentType ?? 'tus documentos de identidad' }} en esta revisión.
        Para continuar con la validación, vuelve a subir el documento solicitado desde tu perfil.
    </p>

    @if (!empty($rejectionReason))
        <div style="
            background-color:#fff7ed;
            border:1px solid #fed7aa;
            border-radius:8px;
            padding:14px 16px;
            margin:18px 0;
        ">
            <p style="font-size:13px; color:#9a3412; margin:0 0 6px; font-weight:700;">
                Motivo indicado por el equipo MindMeet
            </p>
            <p style="font-size:14px; color:#444444; line-height:1.6; margin:0;">
                {{ $rejectionReason }}
            </p>
        </div>
    @else
        <p style="font-size:14px; color:#444444; line-height:1.6;">
            Revisa que la imagen sea clara, legible, completa y que corresponda al documento solicitado.
        </p>
    @endif

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Por favor, accede a tu perfil y vuelve a subir {{ $documentType ?? 'tus documentos de identidad' }}
        con buena iluminación, sin cortes y con la información visible.
    </p>

    <div style="text-align:center; margin-top:25px;">
        <a href="{{ $url ?? rtrim(config('app.front_url_psicologo') ?: config('app.front_url') ?: config('app.frontend_url'), '/') . '/dashboard' }}"
            style="background-color:#0077b6;
                  color:#ffffff;
                  padding:12px 28px;
                  text-decoration:none;
                  border-radius:5px;
                  font-weight:600;
                  display:inline-block;
                  font-size:14px;">
            Actualizar mis documentos
        </a>
    </div>

    <p style="font-size:14px; color:#444444; line-height:1.6; margin-top:25px;">
        Si tienes alguna duda, puedes contactarnos y con gusto te ayudaremos.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:30px;">
        <strong>Equipo MindMeet</strong>
    </p>
@endsection
