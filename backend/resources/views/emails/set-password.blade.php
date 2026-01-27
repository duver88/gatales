<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a Gatales</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #1a1a1a;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; border-collapse: collapse; background-color: #212121; border-radius: 12px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px 40px; text-align: center; background-color: #171717;">
                            <h1 style="margin: 0; font-size: 32px; font-weight: bold; color: #10A37F;">Gatales</h1>
                            <p style="margin: 10px 0 0 0; color: #888888; font-size: 14px;">Tu asistente de guiones de video</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px 0; color: #ECECEC; font-size: 24px; font-weight: 600;">
                                ¡Hola {{ $user->name }}!
                            </h2>

                            <p style="margin: 0 0 20px 0; color: #CCCCCC; font-size: 16px; line-height: 1.6;">
                                ¡Bienvenido a Gatales! Tu suscripción ha sido activada exitosamente.
                            </p>

                            <p style="margin: 0 0 30px 0; color: #CCCCCC; font-size: 16px; line-height: 1.6;">
                                Para comenzar a crear guiones increíbles, primero necesitas establecer tu contraseña. Haz clic en el botón de abajo:
                            </p>

                            <!-- Button -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="{{ $setPasswordUrl }}" style="display: inline-block; padding: 16px 40px; background-color: #10A37F; color: #FFFFFF; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 8px;">
                                            Establecer mi contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; color: #888888; font-size: 14px; line-height: 1.6;">
                                Este enlace expirará en 48 horas. Si no solicitaste esta cuenta, puedes ignorar este correo.
                            </p>

                            <!-- Link fallback -->
                            <p style="margin: 20px 0 0 0; color: #666666; font-size: 12px; line-height: 1.6; word-break: break-all;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                <a href="{{ $setPasswordUrl }}" style="color: #10A37F;">{{ $setPasswordUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #171717; text-align: center; border-top: 1px solid #333333;">
                            <p style="margin: 0; color: #666666; font-size: 12px;">
                                © {{ date('Y') }} Gatales. Todos los derechos reservados.
                            </p>
                            <p style="margin: 10px 0 0 0; color: #555555; font-size: 11px;">
                                Este correo fue enviado porque compraste una suscripción en nuestra plataforma.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
