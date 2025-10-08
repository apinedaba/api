<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $name }} | MindMeet</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- SEO humano: la página real del perfil -->
  <link rel="canonical" href="{{ $spaUrl }}"/>

  <!-- Open Graph para FB/WA/LinkedIn -->
  <meta property="og:type" content="profile">
  <meta property="og:title" content="{{ $name }} | MindMeet">
  <meta property="og:description" content="{{ $bio }}">
  <meta property="og:url" content="{{ $shareUrl }}"><!-- PROXY, no el SPA -->
  <meta property="og:image" content="{{ $ogImage }}">
  <meta property="og:image:secure_url" content="{{ $ogImage }}">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $name }} | MindMeet">
  <meta name="twitter:description" content="{{ $bio }}">
  <meta name="twitter:image" content="{{ $ogImage }}">

  <!-- Redirigir SOLO a humanos al PERFIL (no al home) -->
  <meta http-equiv="refresh" content="0; url={{ $spaUrl }}">
  <script>location.replace("{{ $spaUrl }}");</script>
</head>
<body></body>
</html>
