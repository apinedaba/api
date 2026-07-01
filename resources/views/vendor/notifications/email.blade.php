@component('mail::message')
{{ $greeting ?? 'Hola,' }}

@foreach ($introLines as $line)
{{ $line }}

@endforeach

@isset($actionText)
@component('mail::button', ['url' => $actionUrl, 'color' => 'primary'])
{{ $actionText }}
@endcomponent
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach

Saludos,<br>
El equipo de MindMeet

@isset($actionText)
@slot('subcopy')
Si tienes problemas con el boton "{{ $actionText }}", copia y pega este enlace en tu navegador: {{ $actionUrl }}
@endslot
@endisset
@endcomponent
