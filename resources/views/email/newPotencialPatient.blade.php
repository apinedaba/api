<!DOCTYPE html>
<html xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="es">

<head>
    <title>Nueva Solicitud de Paciente - MindMeet</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900"
        rel="stylesheet" type="text/css">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', Arial, sans-serif;
            -webkit-text-size-adjust: none;
            text-size-adjust: none;
        }

        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: inherit !important;
        }

        @media (max-width:520px) {
            .row-content {
                width: 100% !important;
            }

            .stack .column {
                width: 100%;
                display: block;
            }
        }
    </style>
</head>

<body style="background-color: #f4f7f6; margin: 0; padding: 0;">
    <table class="nl-container" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation"
        style="background-color: #f4f7f6; padding-top: 20px; padding-bottom: 20px;">
        <tbody>
            <tr>
                <td>
                    <table class="row-content stack" align="center" border="0" cellpadding="0" cellspacing="0"
                        role="presentation"
                        style="background-color: #ffffff; color: #000000; width: 550px; margin: 0 auto; border-radius: 12px; overflow: hidden;"
                        width="550">
                        <tbody>
                            <tr>
                                <td style="padding: 30px 20px; text-align: center; background-color: #ffffff;">
                                    <img src="https://ad7fe8c786.imgdist.com/pub/bfra/6evt2bq1/12g/tot/6a9/MindMeet.png"
                                        style="display: inline-block; height: auto; border: 0; width: 160px;"
                                        width="160" alt="MindMeet Logo">
                                </td>
                            </tr>

                            <tr>
                                <td style="padding: 0;">
                                    <img src="https://ad7fe8c786.imgdist.com/pub/bfra/6evt2bq1/ql3/tig/374/Blue%20Dark%20Blue%20and%20Yellow%20Illustrative%20Psychology%20in%20Life%20Presentation%20.png"
                                        style="display: block; height: auto; border: 0; width: 100%;" width="550">
                                </td>
                            </tr>

                            <tr>
                                <td style="padding: 30px 40px;">
                                    <h1
                                        style="margin: 0; color: #00c3b7; font-size: 24px; font-weight: 700; line-height: 1.2; text-align: center;">
                                        ¡Un posible paciente esta interesado en ti!
                                    </h1>

                                    <div style="color: #444; font-size: 16px; line-height: 1.6; margin-top: 25px;">
                                        <p style="margin: 0;">Hola <strong>{{ $user->name }}</strong>,</p>
                                        <p style="margin: 10px 0 20px 0;">Un posible paciente esta interesado en ti,
                                            contactalo lo antes posible y no pierdas esta oportunidad, los datos del
                                            paciente son:</p>

                                        <div
                                            style="background-color: #f0fdfc; border-radius: 10px; padding: 20px; border: 1px solid #e0f2f1;">
                                            <p style="margin: 8px 0;"><strong>👤 Nombre:</strong> {{ $consulta->nombre
                                                }}</p>
                                            <p style="margin: 8px 0;"><strong>📧 Correo:</strong> <a
                                                    href="mailto:{{ $consulta->email }}"
                                                    style="color: #00c3b7; text-decoration: none;">{{ $consulta->email
                                                    }}</a></p>
                                            <p style="margin: 8px 0;"><strong>📞 Teléfono:</strong> {{
                                                $consulta->telefono }}</p>
                                            <p style="margin: 8px 0;"><strong>🧠 Especialidad:</strong> {{ $especialidad
                                                ?? 'Sin especificar' }}</p>
                                            <p style="margin: 8px 0;"><strong>💰 Precio:</strong> {{ $consulta->precio
                                                ?? 'Sin especificar' }}</p>
                                            <p style="margin: 8px 0;"><strong>📅 Fecha:</strong> {{ $consulta->fecha
                                                ?? 'Sin especificar' }}</p>
                                            <p style="margin: 8px 0;"><strong>⏰ Hora:</strong> {{ $consulta->hora
                                                ?? 'Sin especificar' }}</p>

                                            <hr style="border: 0; border-top: 1px solid #d1e8e7; margin: 15px 0;">

                                            <p style="margin: 5px 0;"><strong>💬 Motivo de la consulta:</strong></p>
                                            <p
                                                style="font-style: italic; color: #555; background: #ffffff; padding: 15px; border-radius: 8px; border-left: 4px solid #00c3b7; margin-top: 10px;">
                                                "{{ $consulta->motivo }}"
                                            </p>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding: 20px; background-color: #f9f9f9; text-align: center;">
                                    <p style="color: #999; font-size: 12px; margin: 0;">
                                        Este correo fue enviado automáticamente desde el sistema de gestión de
                                        <strong>MindMeet</strong>.<br>
                                        © {{ date('Y') }} MindMeet - Servicios Psicológicos.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
