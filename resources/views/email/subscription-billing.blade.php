@extends('email.layouts.base')

@section('content')
    <h1 style="font-size:24px; color:#0f172a; margin:0 0 16px; font-weight:700;">
        {{ $title }}
    </h1>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 14px;">
        Hola {{ $name ?? '' }},
    </p>

    <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 14px;">
        {{ $body }}
    </p>

    @if (!empty($detail))
        <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 14px;">
            {{ $detail }}
        </p>
    @endif

    @if (!empty($secondary))
        <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 24px;">
            {{ $secondary }}
        </p>
    @endif

    <a href="{{ $actionUrl }}" style="display:inline-block; padding:12px 22px; background:#0077b6; color:#ffffff; text-decoration:none; border-radius:6px; font-size:14px; font-weight:600;">
        {{ $actionLabel }}
    </a>
@endsection
