<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscripción Cancelada</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 0;">
                <table role="presentation" style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">{{ config('app.name', 'Gatales') }}</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #333333; margin: 0 0 20px 0; font-size: 24px;">
                                Hola {{ $user->name }},
                            </h2>

                            <p style="color: #666666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Lamentamos informarte que tu suscripción ha sido cancelada.
                                <strong>¡Te vamos a extrañar!</strong>
                            </p>

                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; margin: 25px 0; border-radius: 4px;">
                                <p style="color: #856404; margin: 0; font-size: 15px;">
                                    <strong>⚠️ Tu cuenta ha sido desactivada</strong><br>
                                    Ya no tendrás acceso a las funcionalidades premium hasta que reactives tu suscripción.
                                </p>
                            </div>

                            <p style="color: #666666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Sabemos que a veces las circunstancias cambian, pero queremos que sepas que
                                <strong>siempre serás bienvenido/a de vuelta</strong>.
                            </p>

                            <h3 style="color: #333333; margin: 30px 0 15px 0; font-size: 18px;">
                                ¿Por qué volver?
                            </h3>

                            <ul style="color: #666666; font-size: 15px; line-height: 1.8; padding-left: 20px; margin: 0 0 25px 0;">
                                <li>✨ Acceso ilimitado a todas las funcionalidades</li>
                                <li>🚀 Nuevas mejoras y características constantemente</li>
                                <li>💬 Soporte prioritario</li>
                                <li>🎁 Conserva tu historial y configuraciones</li>
                            </ul>

                            <!-- CTA Button -->
                            <table role="presentation" style="width: 100%; margin: 30px 0;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ $reactivateUrl }}" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 15px 40px; border-radius: 30px; font-size: 16px; font-weight: bold; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                                            🔄 Reactivar mi suscripción
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #999999; font-size: 14px; line-height: 1.6; margin: 25px 0 0 0; text-align: center;">
                                Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.<br>
                                Estamos aquí para ti.
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
