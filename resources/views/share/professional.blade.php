<!-- resources/views/share/professional.blade.php -->
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8">
<title>{{ $name }} | MindMeet</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- SEO humano -->
<link rel="canonical" href="{{ $spaUrl }}"/>

<!-- Open Graph -->
<meta property="og:type" content="profile">
<meta property="og:title" content="{{ $name }} | MindMeet">
<meta property="og:description" content="{{ $bio }}">
<meta property="og:url" content="{{ $shareUrl }}"><!-- PROXY, no el home ni el SPA -->
<meta property="og:image" content="{{ $img }}"><!-- explícito -->
<meta property="og:image:secure_url" content="{{ $img }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:site_name" content="MindMeet">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $name }} | MindMeet">
<meta name="twitter:description" content="{{ $bio }}">
<meta name="twitter:image" content="{{ $img }}">

<!-- Redirección SOLO para humanos (los bots ignoran JS) -->
<meta http-equiv="refresh" content="0; url={{ $spaUrl }}">
<script>location.replace("{{ $spaUrl }}");</script>
</head><body></body></html>
