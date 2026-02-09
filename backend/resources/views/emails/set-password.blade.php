<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a El Cursales</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #000000;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #111111; border-radius: 12px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 36px 40px 28px 40px; text-align: center; background-color: #0d0d0d;">
                            <img src="https://elcursales.ai/logo-192.png" alt="El Cursales" width="64" height="64" style="display: block; margin: 0 auto 16px auto; border-radius: 12px;" />
                            <h1 style="margin: 0; font-size: 28px; font-weight: bold; color: #00be88;">El Cursales</h1>
                            <p style="margin: 8px 0 0 0; color: #888888; font-size: 13px;">Tu asistente de guiones de video</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 36px 40px 40px 40px;">
                            <h2 style="margin: 0 0 20px 0; color: #ECECEC; font-size: 22px; font-weight: 600;">
                                Hola {{ $user->name }},
                            </h2>

                            <p style="margin: 0 0 16px 0; color: #CCCCCC; font-size: 15px; line-height: 1.6;">
                                Bienvenido a El Cursales. Tu suscripcion ha sido activada exitosamente.
                            </p>

                            <p style="margin: 0 0 28px 0; color: #CCCCCC; font-size: 15px; line-height: 1.6;">
                                Para comenzar a crear guiones, primero necesitas establecer tu contrasena. Haz clic en el boton de abajo:
                            </p>

                            <!-- Button -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td align="center" style="padding: 16px 0;">
                                        <a href="{{ $setPasswordUrl }}" style="display: inline-block; padding: 14px 36px; background-color: #00be88; color: #FFFFFF; text-decoration: none; font-size: 15px; font-weight: 600; border-radius: 8px;">
                                            Establecer mi contrasena
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 28px 0 0 0; color: #888888; font-size: 13px; line-height: 1.6;">
                                Este enlace expirara en 48 horas. Si no solicitaste esta cuenta, puedes ignorar este correo.
                            </p>

                            <!-- Link fallback -->
                            <p style="margin: 16px 0 0 0; color: #666666; font-size: 12px; line-height: 1.6; word-break: break-all;">
                                Si el boton no funciona, copia y pega este enlace en tu navegador:<br>
                                <a href="{{ $setPasswordUrl }}" style="color: #00be88;">{{ $setPasswordUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; background-color: #0a0a0a; text-align: center; border-top: 1px solid #222222;">
                            <p style="margin: 0; color: #555555; font-size: 12px;">
                                &copy; {{ date('Y') }} El Cursales. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
