<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>MindMeet</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f6f8; font-family: 'Segoe UI', Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8; padding:30px 0;">
        <tr>
            <td align="center">

                <!-- CONTENEDOR -->
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background:#ffffff; border-radius:10px; overflow:hidden;">

                    <!-- HEADER / IMAGEN -->
                    <tr>
                        <td style="padding:20px;">
                            <img src="https://res.cloudinary.com/dabwvv94x/image/upload/v1765847998/8b123a835cc4a89edd2945f4ac5ad1bd_gadawy.png"
                                alt="MindMeet" style="width:100%; display:block;">
                        </td>
                    </tr>

                    <!-- CONTENIDO -->
                    <tr>
                        <td style="padding:30px;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- FOOTER REDES -->
                    <tr>
                        <td style="background:#0077b6; padding:20px; text-align:center;">
                            <a href="https://facebook.com/mindmeet" style="margin:0 8px;">
                                <img src="https://res.cloudinary.com/dabwvv94x/image/upload/v1765847998/75f540192117fb9493ec6b2295efddd3_mned8q.png"
                                    width="28">
                            </a>
                            <a href="https://instagram.com/mindmeet" style="margin:0 8px;">
                                <img src="https://res.cloudinary.com/dabwvv94x/image/upload/v1765847998/82eaed0cd10c13e9d3b74bcc132fb326_rd1jvl.png"
                                    width="28">
                            </a>
                            <a href="https://linkedin.com/company/mindmeet" style="margin:0 8px;">
                                <img src="https://res.cloudinary.com/dabwvv94x/image/upload/v1765847998/c9a8c16cd390ed2b5a2fb62eb149e2f0_wtvixd.png"
                                    width="28">
                            </a>
                            <a href="https://tiktok.com/@mindmeet" style="margin:0 8px;">
                                <img src="https://res.cloudinary.com/dabwvv94x/image/upload/v1765847998/6ac1b2b01aebc0c7c0243dca11c1403b_n7omcz.png"
                                    width="28">
                            </a>

                            <p style="color:#ffffff; font-size:12px; margin-top:15px;">
                                contacto@mindmeet.mx<br>
                                © {{ date('Y') }} MindMeet
                            </p>
                        </td>
                    </tr>

                </table>
                <!-- /CONTENEDOR -->

            </td>
        </tr>
    </table>

</body>

</html>