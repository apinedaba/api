@extends('emails.layouts.mindmeet')

@section('content')

    <h1 style="
        font-size:22px;
        color:#000000;
        margin-bottom:20px;
        font-weight:600;
    ">
        Hubo un problema con tu pago
    </h1>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Detectamos que el <strong>intento de cobro de tu membresía en MindMeet no pudo completarse</strong>.
        Esto puede deberse a un problema con el método de pago o con la autorización del banco.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Para evitar interrupciones en tu acceso a la plataforma, te recomendamos
        gestionar tu membresía o realizar el pago manualmente.
    </p>

    <!-- CTA -->
    <table cellpadding="0" cellspacing="0" style="margin-top:30px;">
        <tr>
            <td>
                <a href="https://minder.mindmeet.com.mx/perfil/suscripcion" style="
                   display:inline-block;
                   padding:12px 24px;
                   background:#f97316;
                   color:#ffffff;
                   text-decoration:none;
                   border-radius:6px;
                   font-size:14px;
                   font-weight:600;">
                    Gestionar mi membresía
                </a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px; color:#555555; margin-top:30px;">
        Si el pago no se regulariza, tu membresía podría verse afectada.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:25px;">
        Estamos aquí para ayudarte si tienes alguna duda o necesitas apoyo.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:20px;">
        <strong>Equipo MindMeet</strong>
    </p>

@endsection