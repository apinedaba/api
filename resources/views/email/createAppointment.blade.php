<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MindMeet | Sesion programada</title>
</head>
<body style="margin:0;padding:0;background:#f4f8fb;font-family:Arial,sans-serif;color:#15324b;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f8fb;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:24px;overflow:hidden;">
          <tr>
            <td style="background:linear-gradient(135deg,#0f6bdc 0%,#13b8c8 100%);padding:28px 32px 22px;">
              <img src="https://res.cloudinary.com/dabwvv94x/image/upload/v1734551039/eliotrope_1_zbbhby.svg" alt="MindMeet" style="width:140px;max-width:100%;display:block;margin-bottom:22px;">
              <p style="margin:0 0 8px;color:#dff7ff;font-size:13px;letter-spacing:.08em;text-transform:uppercase;">MindMeet</p>
              <h1 style="margin:0;color:#ffffff;font-size:28px;line-height:1.2;">Tu sesion ya quedo programada</h1>
              <p style="margin:14px 0 0;color:#eafcff;font-size:16px;line-height:1.6;">
                Hola {{ $pacienteName }}, dimos un paso importante para cuidar tu proceso. Aqui tienes la informacion esencial de tu proxima sesion con {{ $user->name }}.
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:28px 32px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7fbff;border:1px solid #dbeaf6;border-radius:18px;">
                <tr>
                  <td style="padding:22px 24px;">
                    <p style="margin:0 0 14px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#0f6bdc;">Detalles de la sesion</p>
                    <p style="margin:0 0 10px;font-size:16px;line-height:1.6;"><strong>Fecha:</strong> {{ $fecha }}</p>
                    <p style="margin:0 0 10px;font-size:16px;line-height:1.6;"><strong>Horario:</strong> {{ $hora }}</p>
                    <p style="margin:0 0 10px;font-size:16px;line-height:1.6;"><strong>Duracion:</strong> {{ $interval }}</p>
                    <p style="margin:0 0 10px;font-size:16px;line-height:1.6;"><strong>Tipo de sesion:</strong> {{ $tipoSesion }}</p>
                    <p style="margin:0;font-size:16px;line-height:1.6;"><strong>Formato:</strong> {{ $formato }}</p>
                  </td>
                </tr>
              </table>

              @if ($isOnline)
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;background:#e9fbf8;border:1px solid #bcefe5;border-radius:18px;">
                  <tr>
                    <td style="padding:20px 24px;">
                      <p style="margin:0 0 10px;font-size:18px;color:#0e7f71;"><strong>Preparacion recomendada</strong></p>
                      <p style="margin:0;font-size:15px;line-height:1.7;color:#1d4a54;">
                        Para aprovechar mejor este espacio, te sugerimos conectarte desde un lugar tranquilo, con privacidad y sin interrupciones. Regalarte este momento tambien es una forma de cuidado personal.
                      </p>
                    </td>
                  </tr>
                </table>
              @endif

              <p style="margin:22px 0 0;font-size:16px;line-height:1.8;color:#35556d;">
                Respira profundo y recuerda que no necesitas llegar perfecto a la sesion: solo llegar presente. Cada encuentro es una oportunidad para avanzar un poco mas hacia tu bienestar.
              </p>

              <div style="margin-top:28px;">
                <a href="{{ $dashboardUrl }}" style="display:inline-block;background:#0f6bdc;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:999px;font-size:15px;font-weight:bold;">
                  Ver sesion en MindMeet
                </a>
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:0 32px 28px;">
              <p style="margin:0;font-size:13px;line-height:1.7;color:#6d879a;">
                Si necesitas apoyo adicional o hay algun cambio importante, puedes revisar tu cuenta en MindMeet o contactar a tu profesional.
              </p>
            </td>
          </tr>

          <tr>
            <td style="background:#f7fbff;padding:18px 32px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#7b8e9e;">&copy; {{ date('Y') }} MindMeet. Un espacio para acompanar tu bienestar emocional.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
