@extends('email.layouts.base')

@section('content')
    <h1 style="font-size:22px;color:#111827;margin-bottom:20px;font-weight:600;">Actualización de tu membresía MindMeet</h1>

    <p style="font-size:14px;color:#374151;line-height:1.7;">Hola {{ $name }},</p>

    @if($action === 'revoke_lifetime')
        <p style="font-size:14px;color:#374151;line-height:1.7;">
            Hemos detectado que tu cuenta lleva mucho tiempo inactiva desde que te asignamos una membresía permanente gratuita. Por este motivo, este beneficio ha sido retirado.
        </p>
    @else
        <p style="font-size:14px;color:#374151;line-height:1.7;">
            Tu suscripción a MindMeet fue cancelada manualmente por nuestro equipo.
            @if($refunded) El último cobro elegible también fue reembolsado a través de Stripe. @endif
        </p>
    @endif

    <p style="font-size:14px;color:#374151;line-height:1.7;">
        Para continuar usando MindMeet y disfrutar de todas sus herramientas, adquiere un plan desde <strong>$149 MXN al mes</strong>.
    </p>

    <table cellpadding="0" cellspacing="0" style="margin-top:24px;"><tr><td>
        <a href="{{ $plansUrl }}" style="display:inline-block;padding:12px 22px;background:#0077b6;color:#fff;text-decoration:none;border-radius:7px;font-size:14px;font-weight:600;">Ver planes</a>
    </td></tr></table>

    <p style="font-size:13px;color:#4b5563;line-height:1.7;margin-top:26px;">
        Si crees que estamos cometiendo un error, comunícate con nosotros por WhatsApp al
        <a href="https://wa.me/5216635385134" style="color:#0077b6;">+52 1 663 538 5134</a>.
    </p>
@endsection
