<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #12304A; }

        /* CR80 vertical: 53.98 x 85.60 mm. Every element is positioned within it
           so Dompdf cannot move a footer or the QR to another page. */
        .card { position: relative; width: 53.98mm; height: 85.60mm; overflow: hidden; background: #fff; page-break-after: always; }
        .card:last-child { page-break-after: auto; }
        .bar { position: absolute; top: 0; left: 0; right: 0; height: 3.6mm; background: #1358A9; }
        .ornament { position: absolute; border: .22mm solid #D7F1FE; border-radius: 50%; opacity: .9; }
        .ornament-left { width: 22mm; height: 22mm; left: -13mm; top: 31mm; }
        .ornament-right { width: 21mm; height: 21mm; right: -14mm; top: 46mm; }
        .logo { position: absolute; top: 8mm; left: 8.5mm; width: 37mm; height: auto; }

        .photo, .photo-placeholder { position: absolute; top: 22mm; left: 12.49mm; width: 29mm; height: 29mm; border-radius: 50%; }
        .photo { object-fit: cover; background: #EAF8FE; }
        .photo-placeholder { background: #EAF8FE; border: .22mm solid #D7F1FE; }
        .name { position: absolute; top: 53.5mm; left: 4mm; right: 4mm; margin: 0; font-size: 9.4pt; line-height: 1.13; font-weight: bold; text-align: center; }
        .pill { position: absolute; top: 62mm; left: 8mm; right: 8mm; height: 6.5mm; padding-top: 1.55mm; border-radius: 6mm; background: #1358A9; color: #fff; font-size: 6.8pt; line-height: 1; font-weight: bold; letter-spacing: .6pt; text-align: center; }
        .line { position: absolute; left: 4mm; right: 4mm; top: 72mm; border-top: .35mm solid #BDEBFF; }
        .front-foot { position: absolute; top: 74.4mm; left: 3.5mm; right: 3.5mm; margin: 0; font-size: 5.65pt; line-height: 1.25; text-align: center; }
        .verified { color: #00A99D; font-weight: bold; }
        .id { font-weight: bold; word-break: break-word; }

        .back-title { position: absolute; top: 22mm; left: 4mm; right: 4mm; margin: 0; font-size: 10.5pt; line-height: 1.2; font-weight: bold; text-align: center; }
        .qr { position: absolute; top: 31mm; left: 13.49mm; width: 27mm; height: 27mm; }
        .back-copy { position: absolute; top: 60mm; left: 5mm; right: 5mm; margin: 0; font-size: 6.7pt; line-height: 1.4; text-align: center; }
        .back-unavailable { position: absolute; top: 40mm; left: 6mm; right: 6mm; margin: 0; font-size: 7.2pt; line-height: 1.5; text-align: center; }
        .back-details { position: absolute; top: 73.8mm; left: 4mm; right: 4mm; margin: 0; font-size: 5.8pt; line-height: 1.3; text-align: center; }
        .site { position: absolute; top: 78.2mm; left: 4mm; right: 4mm; margin: 0; color: #1358A9; font-size: 6.3pt; font-weight: bold; text-align: center; }
        .notice { position: absolute; top: 82.5mm; left: 4mm; right: 4mm; margin: 0; font-size: 5.2pt; text-align: center; }
    </style>
</head>
<body>
@php($logo = 'https://res.cloudinary.com/dabwvv94x/image/upload/v1764650408/MindMeet_1280_x_350_px_c6yojr.png')
@php($backLogo = 'https://res.cloudinary.com/dabwvv94x/image/upload/c_scale,w_1280/v1764650408/MindMeet_1280_x_350_px_c6yojr.png')
@php($roleLabel = match ($credential['userType']) {
    'psychologist' => ($credential['genderLabel'] === 'female' || $credential['genderLabel'] === 'femenino') ? 'PSICÓLOGA' : 'PSICÓLOGO',
    'superadmin' => 'SUPERADMIN',
    default => 'PACIENTE',
})
<section class="card">
    <div class="bar"></div><div class="ornament ornament-left"></div><div class="ornament ornament-right"></div>
    <img class="logo" src="{{ $logo }}" alt="MindMeet">
    @if($credential['photoUrl'])
        <img class="photo" src="{{ $credential['photoUrl'] }}" alt="">
    @else
        <div class="photo-placeholder"></div>
    @endif
    <p class="name">{{ $credential['fullName'] }}</p>
    <div class="pill">{{ $roleLabel }}</div>
    <div class="line"></div>
    <p class="front-foot">@if($credential['verified'])<span class="verified">✓ Perfil verificado</span> · @endif<span class="id">ID: {{ $credential['credentialId'] }}</span></p>
</section>

@if($credential['userType'] === 'psychologist')
<section class="card">
    <div class="bar"></div><div class="ornament ornament-left"></div><div class="ornament ornament-right"></div>
    <img class="logo" src="{{ $backLogo }}" alt="MindMeet">
    <p class="back-title">Verifica esta credencial</p>
    @if($credential['qrSvg'])
        <img class="qr" src="{{ $credential['qrSvg'] }}" alt="Código QR">
        <p class="back-copy">Escanea el código para consultar<br>el perfil dentro de MindMeet.</p>
    @else
        <p class="back-unavailable">Tu perfil público debe estar activo para generar el código QR.</p>
    @endif
    <div class="line"></div>
    <p class="back-details id">ID: {{ $credential['credentialId'] }}</p>
    <p class="site">mindmeet.com.mx</p>
    <p class="notice">Credencial personal e intransferible</p>
</section>
@endif
</body>
</html>
