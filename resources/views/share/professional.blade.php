<!-- resources/views/share/professional.blade.php -->
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $name }} | MindMeet</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="canonical" href="{{ $spaUrl }}"/>

  <!-- Open Graph -->
  <meta property="og:type" content="profile">
  <meta property="og:title" content="{{ $name }} | MindMeet">
  <meta property="og:description" content="{{ $bio }}">
  <meta property="og:url" content="{{ $spaUrl }}">
  <meta property="og:image" content="{{ $ogImage }}">
  <meta property="og:image:secure_url" content="{{ $ogImage }}">
  <meta property="og:image:alt" content="{{ $name }}">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $name }} | MindMeet">
  <meta name="twitter:description" content="{{ $bio }}">
  <meta name="twitter:image" content="{{ $ogImage }}">

  <!-- Redirección al SPA para humanos -->
  <meta http-equiv="refresh" content="0; url={{ $spaUrl }}">
  <script>location.replace("{{ $spaUrl }}");</script>
</head>
<body></body>
</html>
