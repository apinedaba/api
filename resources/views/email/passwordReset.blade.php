{{-- resources/views/emails/passwordReset.blade.php --}}

<!DOCTYPE html>
<html>
<head>
    <title>Restablecer Contraseña</title>
</head>
<body>
    <h2>Hola, {{ $userName }}</h2>
    <p>Has solicitado restablecer tu contraseña en MindMeet.</p>
    <p>Usa el siguiente código para completar el proceso. El código es válido por 10 minutos.</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 5px; text-align: center;">
        {{ $code }}
    </p>
    <p>Si no solicitaste este cambio, puedes ignorar este correo de forma segura.</p>
    <p>Saludos,<br>El equipo de MindMeet</p>
</body>
</html>