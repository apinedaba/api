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
        <img src="https://ad7fe8c786.imgdist.com/pub/bfra/6evt2bq1/12g/tot/6a9/MindMeet.png" alt="MindMeet" style="max-width: 150px;">
      </td>
    </tr>
    <tr>
      <td>
        <h2 style="color: #333;">Hola {{ $usuario->name }},</h2>
        <p style="font-size: 16px; color: #555;">
          Queremos informarte que tu cita programada para el <strong>{{ $fecha }}</strong> a las <strong>{{ $hora }}</strong> ha sido:
        </p>

        @php
          $estadoLower = strtolower($estado);
        @endphp

        @if($estadoLower === 'confirm')
          <p style="font-size: 18px; font-weight: bold; color: green;">✅ Confirmada</p>
          <p style="font-size: 16px; color: #555;">
            ¡Gracias por confiar en nosotros! Te esperamos puntual para tu cita.
          </p>
        @elseif($estadoLower === 'cancel')
          <p style="font-size: 18px; font-weight: bold; color: red;">❌ Cancelada</p>
          <p style="font-size: 16px; color: #555;">
            Lamentamos los inconvenientes. Puedes reprogramar tu cita cuando lo desees.
          </p>
        @else
          <p style="font-size: 18px; font-weight: bold; color: #555;">📌 Estado: {{ $estado }}</p>
          <p style="font-size: 16px; color: #555;">
            Te mantendremos informado sobre cualquier cambio.
          </p>
        @endif

        <p style="font-size: 16px; color: #555;">Si tienes alguna pregunta, no dudes en contactar a tu Profesional.</p>

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
