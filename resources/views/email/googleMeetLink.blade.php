<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Enlace de tu sesión en MindMeet</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
</head>

<body style="font-family: 'Poppins', Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0"
        style="max-width: 600px; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
        <tr>
            <td align="center" style="padding-bottom: 20px;">
                <img src="https://ad7fe8c786.imgdist.com/pub/bfra/6evt2bq1/12g/tot/6a9/MindMeet.png" alt="MindMeet"
                    style="max-width: 150px;">
            </td>
        </tr>
        <tr>
            <td>
                <h2 style="color: #333;">Hola {{ $patientName }},</h2>

                @if ($isUpdate)
                    <p style="font-size: 16px; color: #555;">
                        Tu sesión ha sido <strong>actualizada</strong>.
                    </p>
                    @if ($linkChanged)
                        <p style="font-size: 16px; color: #555;">
                            Se ha generado un <strong>nuevo enlace</strong> para acceder a tu sesión:
                        </p>
                    @else
                        <p style="font-size: 16px; color: #555;">
                            Aquí tienes el enlace para acceder a tu sesión:
                        </p>
                    @endif
                @else
                    <p style="font-size: 16px; color: #555;">
                        Tu sesión está <strong>programada</strong> y ya tienes disponible el enlace para unirte.
                    </p>
                @endif

                <div
                    style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007bff;">
                    <p style="margin: 0 0 10px 0; font-size: 16px; color: #333;">
                        <strong>Enlace de Google Meet:</strong>
                    </p>
                    <div style="text-align: center; margin: 15px 0;">
                        <a href="{{ $meetLink }}"
                            style="background-color: #007bff; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            🎥 Unirme a la sesión
                        </a>
                    </div>
                    <p style="margin: 10px 0 0 0; font-size: 14px; color: #666; word-break: break-all;">
                        O copia este enlace: <a href="{{ $meetLink }}"
                            style="color: #007bff;">{{ $meetLink }}</a>
                    </p>
                </div>

                <p style="font-size: 16px; color: #555;">
                    <strong>Fecha y hora:</strong> {{ $fecha }} a las {{ $hora }}
                </p>

                <p style="font-size: 16px; color: #555;">
                    <strong>Importante:</strong> El enlace estará disponible <strong>1 hora antes</strong> del inicio de
                    la sesión.
                </p>

                <p style="font-size: 16px; color: #555;">
                    Si tienes alguna pregunta, no dudes en contactar a tu profesional.
                </p>

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
