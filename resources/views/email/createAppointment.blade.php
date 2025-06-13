<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Estado de tu cita</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Poppins', Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4;">
  <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
    <tr>
      <td align="center" style="padding-bottom: 20px;">
        <img src="https://res.cloudinary.com/dabwvv94x/image/upload/v1734551039/eliotrope_1_zbbhby.svg" alt="MindMeet" style="max-width: 150px;">
      </td>
    </tr>
    <tr>
      <td>
        <h2 style="color: #333;">Hola {{ $pacienteName }},</h2>
        
        <p style="font-size: 16px; color: #555;">
          Te informamos que <strong>{{$user->name}}</strong> agendo una cita contigo.
        </p>
        <p>
          <strong>Fecha:</strong> {{ $fecha }}<br>
          <strong>Hora:</strong> {{ $hora }}<br>
          <strong>Duración:</strong> {{ $interval}}<br>
        </p>
        <p style="font-size: 16px; color: #555;">
          Confirma tu cita haciendo clic en el siguiente enlace:
          <a href="{{ $url }}" style="color: #007bff; text-decoration: none;">Confirmar Cita</a>
        </p>

        <p style="font-size: 16px; color: #555;">Si tienes alguna pregunta, no dudes en responder este correo.</p>

        <p style="font-size: 16px; color: #555;">Saludos,<br>El equipo de MindMeet</p>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding-top: 30px;">
        <small style="color: #aaa;">&copy; {{ date('Y') }} MindMeet. Todos los derechos reservados.</small>
      </td>
    </tr>
  </table>
</body>
</html>
