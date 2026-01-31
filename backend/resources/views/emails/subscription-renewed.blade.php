<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscripción Renovada</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #000000;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; border-collapse: collapse; background-color: #111111; border-radius: 12px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px 40px; text-align: center; background-color: #0d0d0d;">
                            <h1 style="margin: 0; font-size: 32px; font-weight: bold; color: #00be88;">El Cursales</h1>
                            <p style="margin: 10px 0 0 0; color: #888888; font-size: 14px;">Tu asistente de guiones de video</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px 0; color: #ECECEC; font-size: 24px; font-weight: 600;">
                                🎉 ¡Hola {{ $user->name }}!
                            </h2>

                            <p style="margin: 0 0 20px 0; color: #CCCCCC; font-size: 16px; line-height: 1.6;">
                                <strong style="color: #ECECEC;">¡Gracias por continuar con nosotros!</strong> Tu suscripción ha sido renovada exitosamente.
                            </p>

                            <!-- Token Balance Box -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 25px 0;">
                                <tr>
                                    <td style="background-color: #1a2e29; border: 1px solid #00be88; padding: 25px; border-radius: 8px; text-align: center;">
                                        <p style="margin: 0 0 5px 0; color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                            Tu nuevo balance de tokens
                                        </p>
                                        <p style="margin: 0; color: #00be88; font-size: 42px; font-weight: bold;">
                                            {{ number_format($tokensBalance) }}
                                        </p>
                                        <p style="margin: 10px 0 0 0; color: #666666; font-size: 13px;">
                                            tokens disponibles este mes
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Success Box -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 25px 0;">
                                <tr>
                                    <td style="background-color: #1a2e29; border-left: 4px solid #00be88; padding: 15px 20px; border-radius: 4px;">
                                        <p style="margin: 0; color: #00be88; font-size: 15px;">
                                            <strong>✅ Tu cuenta está activa</strong><br>
                                            <span style="color: #6ee7b7;">Continúa disfrutando de todas las funcionalidades premium.</span>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <h3 style="margin: 30px 0 15px 0; color: #ECECEC; font-size: 18px; font-weight: 600;">
                                ¿Qué puedes hacer ahora?
                            </h3>

                            <ul style="margin: 0 0 25px 0; padding-left: 20px; color: #CCCCCC; font-size: 15px; line-height: 2;">
                                <li>💬 Continuar tus conversaciones</li>
                                <li>🚀 Explorar nuevas funcionalidades</li>
                                <li>📊 Revisar tu uso de tokens en el panel</li>
                                <li>⭐ Aprovechar al máximo tu suscripción</li>
                            </ul>

                            <!-- Button -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="{{ config('services.frontend.url', config('app.url')) }}" style="display: inline-block; padding: 16px 40px; background-color: #00be88; color: #FFFFFF; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 8px;">
                                            🚀 Ir a mi cuenta
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; color: #888888; font-size: 14px; line-height: 1.6; text-align: center;">
                                ¡Gracias por confiar en nosotros!<br>
                                Estamos comprometidos en brindarte la mejor experiencia.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #0d0d0d; text-align: center; border-top: 1px solid #2a2a2a;">
                            <p style="margin: 0; color: #666666; font-size: 12px;">
                                © {{ date('Y') }} El Cursales. Todos los derechos reservados.
                            </p>
                            <p style="margin: 10px 0 0 0; color: #555555; font-size: 11px;">
                                Este correo fue enviado a {{ $user->email }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
