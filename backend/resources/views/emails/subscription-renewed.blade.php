<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscripción Renovada</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 0;">
                <table role="presentation" style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">{{ config('app.name', 'Gatales') }}</h1>
                            <p style="color: #ffffff; margin: 10px 0 0 0; font-size: 40px;">🎉</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #333333; margin: 0 0 20px 0; font-size: 24px;">
                                ¡Hola {{ $user->name }}!
                            </h2>

                            <p style="color: #666666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                <strong>¡Gracias por continuar con nosotros!</strong> Tu suscripción ha sido renovada exitosamente.
                            </p>

                            <!-- Token Balance Box -->
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; padding: 25px; margin: 25px 0; text-align: center;">
                                <p style="color: rgba(255,255,255,0.9); margin: 0 0 5px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                    Tu nuevo balance de tokens
                                </p>
                                <p style="color: #ffffff; margin: 0; font-size: 42px; font-weight: bold;">
                                    {{ number_format($tokensBalance) }}
                                </p>
                                <p style="color: rgba(255,255,255,0.8); margin: 10px 0 0 0; font-size: 13px;">
                                    tokens disponibles este mes
                                </p>
                            </div>

                            <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px 20px; margin: 25px 0; border-radius: 4px;">
                                <p style="color: #155724; margin: 0; font-size: 15px;">
                                    <strong>✅ Tu cuenta está activa</strong><br>
                                    Continúa disfrutando de todas las funcionalidades premium.
                                </p>
                            </div>

                            <h3 style="color: #333333; margin: 30px 0 15px 0; font-size: 18px;">
                                ¿Qué puedes hacer ahora?
                            </h3>

                            <ul style="color: #666666; font-size: 15px; line-height: 1.8; padding-left: 20px; margin: 0 0 25px 0;">
                                <li>💬 Continuar tus conversaciones</li>
                                <li>🚀 Explorar nuevas funcionalidades</li>
                                <li>📊 Revisar tu uso de tokens en el panel</li>
                                <li>⭐ Aprovechar al máximo tu suscripción</li>
                            </ul>

                            <!-- CTA Button -->
                            <table role="presentation" style="width: 100%; margin: 30px 0;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ config('app.frontend_url', config('app.url')) }}" style="display: inline-block; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: #ffffff; text-decoration: none; padding: 15px 40px; border-radius: 30px; font-size: 16px; font-weight: bold; box-shadow: 0 4px 15px rgba(17, 153, 142, 0.4);">
                                            🚀 Ir a mi cuenta
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #999999; font-size: 14px; line-height: 1.6; margin: 25px 0 0 0; text-align: center;">
                                ¡Gracias por confiar en nosotros!<br>
                                Estamos comprometidos en brindarte la mejor experiencia.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 25px 30px; text-align: center; border-top: 1px solid #eeeeee;">
                            <p style="color: #999999; font-size: 13px; margin: 0;">
                                © {{ date('Y') }} {{ config('app.name', 'Gatales') }}. Todos los derechos reservados.
                            </p>
                            <p style="color: #999999; font-size: 12px; margin: 10px 0 0 0;">
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
