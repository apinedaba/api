@extends('email.layouts.base')

@section('content')

    <h1 style="font-size:22px; color:#0f172a; margin-bottom:20px; font-weight:700;">
        No pudimos procesar tu renovación
    </h1>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        Detectamos un problema al intentar cobrar tu suscripción de <strong>MindMeet</strong>.
        Esto suele pasar cuando el banco rechaza el cargo o cuando el método de pago necesita actualización.
    </p>

    <p style="font-size:14px; color:#334155; line-height:1.7;">
        Tu espacio sigue siendo importante para nosotros. Si actualizas tu forma de pago a tiempo,
        podrás continuar con tu agenda, tu perfil visible y tus herramientas sin interrupciones.
    </p>

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
                    Revisar mi suscripción
                </a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px; color:#64748b; margin-top:30px;">
        Si el cobro sigue sin completarse, tu suscripción puede pasar a estado pendiente o vencido.
    </p>

    <p style="font-size:14px; color:#334155; margin-top:25px;">
        Si necesitas ayuda, estamos para acompañarte.
    </p>

    <p style="font-size:14px; color:#334155; margin-top:20px;">
        <strong>Equipo MindMeet</strong>
    </p>

@endsection
