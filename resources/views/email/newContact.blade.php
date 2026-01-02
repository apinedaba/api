<!DOCTYPE html>
<html xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="es">

<head>
    <title>Nueva Consulta MindMeet</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900" rel="stylesheet" type="text/css"><style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: 'Poppins', Arial, sans-serif; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: inherit !important; }
        #MessageViewBody a { color: inherit; text-decoration: none; }
        p { line-height: inherit }
        @media (max-width:520px) {
            .row-content { width: 100% !important; }
            .stack .column { width: 100%; display: block; }
        }
    </style>
</head>

<body style="background-color: #FFFFFF; margin: 0; padding: 0; -webkit-text-size-adjust: none; text-size-adjust: none;">
    <table class="nl-container" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #FFFFFF;">
        <tbody>
            <tr>
                <td>
                    <table class="row row-1" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                        <tbody>
                            <tr>
                                <td>
                                    <table class="row-content stack" align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-radius: 0; color: #000000; width: 500px; margin: 0 auto;" width="500">
                                        <tbody>
                                            <tr>
                                                <td class="column column-1" width="100%" style="font-weight: 400; text-align: center; padding-bottom: 5px; padding-top: 20px; vertical-align: middle;">
                                                    <img src="https://ad7fe8c786.imgdist.com/pub/bfra/6evt2bq1/12g/tot/6a9/MindMeet.png" style="display: inline-block; height: auto; border: 0; width: 150px;" width="150" alt="MindMeet Logo">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="row row-2" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                        <tbody>
                            <tr>
                                <td>
                                    <table class="row-content stack" align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color: #000000; width: 500px; margin: 0 auto;" width="500">
                                        <tbody>
                                            <tr>
                                                <td class="column column-1" width="100%" style="text-align: left; padding-bottom: 25px; padding-top: 10px; vertical-align: top;">
                                                    <div align="center" style="line-height:10px">
                                                        <img src="https://ad7fe8c786.imgdist.com/pub/bfra/6evt2bq1/ql3/tig/374/Blue%20Dark%20Blue%20and%20Yellow%20Illustrative%20Psychology%20in%20Life%20Presentation%20.png" style="display: block; height: auto; border: 0; width: 100%; max-width: 500px;" width="500">
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="row row-3" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                        <tbody>
                            <tr>
                                <td>
                                    <table class="row-content stack" align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color: #000000; width: 500px; margin: 0 auto;" width="500">
                                        <tbody>
                                            <tr>
                                                <td class="column column-1" width="100%" style="padding: 10px 20px;">
                                                    <h1 style="margin: 0; color: #00c3b7; font-family: Arial, sans-serif; font-size: 24px; font-weight: 700; line-height: 120%; text-align: left;">
                                                        ¡Tienes una nueva consulta!
                                                    </h1>
                                                    <div style="color:#101112; font-family:Arial, sans-serif; font-size:16px; line-height:1.5; text-align:left; margin-top: 20px;">
                                                        <p style="margin: 0; margin-bottom: 10px;">Hola <strong>Equipo MindMeet</strong>,</p>
                                                        <p style="margin: 0; margin-bottom: 20px;">Un nuevo paciente está interesado en una sesión. Aquí tienes los detalles:</p>
                                                        
                                                        <div style="background-color: #f8faff; border-radius: 8px; padding: 15px; border-left: 4px solid #00c3b7;">
                                                            <p style="margin: 5px 0;"><strong>Nombre:</strong> {{ $consulta->nombre }}</p>
                                                            <p style="margin: 5px 0;"><strong>Correo:</strong> {{ $consulta->email }}</p>
                                                            <p style="margin: 5px 0;"><strong>Teléfono:</strong> {{ $consulta->telefono }}</p>
                                                            <p style="margin: 5px 0;"><strong>Especialidad:</strong> {{ $consulta->tipo_sesion }}</p>
                                                            <p style="margin: 5px 0;"><strong>Motivo:</strong></p>
                                                            <p style="font-style: italic; color: #555;">"{{ $consulta->motivo }}"</p>
                                                        </div>
                                                    </div>

                                                    <p style="color: #888888; font-size: 12px; text-align: center; margin-top: 20px;">
                                                        Este es un mensaje automático generado por el sistema de contacto de MindMeet.
                                                    </p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
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