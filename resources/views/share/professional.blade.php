<!-- resources/views/share/professional.blade.php -->
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $name }} | MindMeet</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- SEO humano -->
  <link rel="canonical" href="{{ $spaUrl }}" />

  <!-- Open Graph (lo que ven FB/WA/LinkedIn) -->
  <meta property="og:type" content="profile">
  <meta property="og:title" content="{{ $name }} | MindMeet">
  <meta property="og:description" content="{{ $bio }}">
  <meta property="og:url" content="{{ $shareUrl }}">  <!-- PROXY, NO el SPA -->
  <meta property="og:image" content="{{ $ogImage }}">
  <meta property="og:image:secure_url" content="{{ $ogImage }}">
  <meta property="og:image:alt" content="{{ $name }}">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $name }} | MindMeet">
  <meta name="twitter:description" content="{{ $bio }}">
  <meta name="twitter:image" content="{{ $ogImage }}">

  <!-- Nada de 301/302. Solo redirección para humanos -->
  <meta http-equiv="refresh" content="0; url={{ $spaUrl }}">
  <script>location.replace("{{ $spaUrl }}");</script>
</head>
<body></body>
</html>
