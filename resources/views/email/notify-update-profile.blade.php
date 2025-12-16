@extends('email.layouts.base')

@section('content')

    <h1 style="
        font-size:22px;
        color:#000000;
        margin-bottom:20px;
        font-weight:600;
    ">
        Deshabilitación de cuenta
    </h1>

    <p style="font-size:15px; color:#333333; margin-bottom:15px;">
        <strong>Tu cuenta ha sido deshabilitada</strong>
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Te informamos que tu cuenta ha sido deshabilitada. Esta acción se realizó
        debido a que no se cumplió con los requisitos necesarios para continuar
        con el proceso.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        A partir de este momento, el acceso y las funcionalidades asociadas a tu
        cuenta permanecerán deshabilitadas.
    </p>

    <p style="font-size:14px; color:#444444; line-height:1.6;">
        Si consideras que se trata de un error o deseas más información,
        puedes ponerte en contacto con nosotros y con gusto revisaremos tu caso.
    </p>

    <p style="font-size:14px; color:#444444; margin-top:30px;">
        <strong>MindMeet</strong>
    </p>

@endsection