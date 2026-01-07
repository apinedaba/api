@extends('email.layouts.base')

@section('content')

    <h1 style="
                    font-size:22px;
                    color:#000000;
                    margin-bottom:20px;
                    font-weight:600;
                ">
        Tu diario en MindMeet
    </h1>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $patient->name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        {!!  $body !!}
    </p>

    <!-- CTA -->
    <table cellpadding="0" cellspacing="0" style="margin-top:30px;">
        <tr>
            <td>
                <a href="https://mindmeet.com.mx/iniciar-sesion" style="
                               display:inline-block;
                               padding:12px 24px;
                               background:#f97316;
                               color:#ffffff;
                               text-decoration:none;
                               border-radius:6px;
                               font-size:14px;
                               font-weight:600;">
                    {{ $subject }}
                </a>
            </td>
        </tr>
    </table>
    <p style="font-size:14px; color:#444444; margin-top:25px;">
        Estamos aquí para ayudarte si tienes alguna duda o necesitas apoyo.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:20px;">
        <strong>Equipo MindMeet</strong>
    </p>

@endsection