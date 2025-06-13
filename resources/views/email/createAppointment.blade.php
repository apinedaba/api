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
        <img src="{{ asset('logo.png') }}" alt="MindMeet" style="max-width: 150px;">
      </td>
    </tr>
    <tr>
      <td>
        <h2 style="color: #333;">Hola {{ $pacienteName }},</h2>
        
        <p>
          Te informamos que <strong>{{$user->name}}</strong> agendo una cita contigo para el día <strong>{{ $paciente->start }}</strong>.
        </p>
        <p>
          Informa a tu profesional de salud si estas de acuerdo o necesitas cancelar la fecha de tu cita.
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
