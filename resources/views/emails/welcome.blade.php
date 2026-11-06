<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <title>Bienvenido a {{ $appName }}</title>
    <style>
        /* Solo lo que los clientes de correo respetan: el resto va inline. */
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; }
        a { color: #059669; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 24px !important; padding-right: 24px !important; }
            .title { font-size: 24px !important; line-height: 32px !important; }
            .button a { display: block !important; }
            .feature { display: block !important; width: 100% !important; padding: 0 0 16px 0 !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9;">
    {{-- Texto de vista previa que muestran Gmail/Outlook junto al asunto. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
        Tu cuenta en {{ $appName }} está lista. Compra, vende y alquila en todo Colombia.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">

                    {{-- Marca --}}
                    <tr>
                        <td align="center" style="padding:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:24px; font-weight:bold; color:#059669; letter-spacing:-0.5px;">
                            {{ $appName }}
                        </td>
                    </tr>

                    {{-- Tarjeta principal --}}
                    <tr>
                        <td style="background-color:#ffffff; border:1px solid #e2e8f0; border-radius:16px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="height:6px; background-color:#059669; border-radius:16px 16px 0 0; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="px" style="padding:40px 48px 8px 48px; font-family:Arial, Helvetica, sans-serif;">
                                        <h1 class="title" style="margin:0 0 16px 0; font-size:28px; line-height:36px; font-weight:800; color:#0f172a;">
                                            ¡Hola, {{ $user->name }}!
                                        </h1>
                                        <p style="margin:0 0 16px 0; font-size:16px; line-height:26px; color:#475569;">
                                            Te damos la bienvenida a <strong style="color:#0f172a;">{{ $appName }}</strong>. Tu cuenta quedó creada con el correo
                                            <strong style="color:#0f172a;">{{ $user->email }}</strong> y ya puedes empezar a usarla.
                                        </p>
                                    </td>
                                </tr>

                                {{-- Qué puede hacer --}}
                                <tr>
                                    <td class="px" style="padding:8px 48px 8px 48px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                @foreach ([
                                                    ['Compra', 'Productos de vendedores de todo Colombia.'],
                                                    ['Vende', 'Publica tus productos y servicios.'],
                                                    ['Alquila', 'Renta lo que necesitas por días.'],
                                                ] as [$featureTitle, $featureText])
                                                    <td class="feature" valign="top" width="33%" style="padding:0 8px 16px 0; font-family:Arial, Helvetica, sans-serif;">
                                                        <p style="margin:0 0 4px 0; font-size:15px; font-weight:bold; color:#059669;">{{ $featureTitle }}</p>
                                                        <p style="margin:0; font-size:14px; line-height:20px; color:#64748b;">{{ $featureText }}</p>
                                                    </td>
                                                @endforeach
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Botón --}}
                                <tr>
                                    <td class="px" align="center" style="padding:16px 48px 40px 48px;">
                                        <table role="presentation" class="button" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="border-radius:10px; background-color:#059669;">
                                                    <a href="{{ $marketplaceUrl }}" target="_blank"
                                                       style="display:inline-block; padding:14px 32px; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:10px;">
                                                        Explorar el marketplace
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin:16px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#94a3b8;">
                                            Si el botón no funciona, copia este enlace en tu navegador:<br>
                                            <a href="{{ $marketplaceUrl }}" style="color:#059669; word-break:break-all;">{{ $marketplaceUrl }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td class="px" align="center" style="padding:24px 48px 0 48px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#94a3b8;">
                            Recibes este correo porque se creó una cuenta en {{ $appName }} con esta dirección.
                            Si no fuiste tú, puedes ignorarlo.<br>
                            &copy; {{ now()->year }} {{ $appName }}. Hecho en Colombia.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
