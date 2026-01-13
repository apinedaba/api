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
        <strong>Se requiere que vuelvas a subir tus documentos de identidad</strong>
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Te informamos que tu verificación de identidad no pudo ser aprobada. Esto puede deberse a:
    </p>

    <ul style="font-size:14px; color:#444444; line-height:1.6; padding-left:20px;">
        <li>La imagen no es clara o está borrosa</li>
        <li>No se puede verificar la información en el documento</li>
        <li>El documento no es legible</li>
        <li>La foto no corresponde con los requisitos solicitados</li>
    </ul>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Por favor, accede a tu perfil y vuelve a subir las imágenes de {{ $documentType ?? 'tus documentos de identidad' }}
        con mayor claridad.
    </p>

    <div style="text-align:center; margin-top:25px;">
        <a href="{{ $url ?? config('app.frontend_url') . '/perfil' }}"
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
