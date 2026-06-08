<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación - {{ $company?->name ?? config('mail.from.name') ?? 'Sistema' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        @media only screen and (max-width: 600px) {
            .container-table {
                width: 100% !important;
                border-radius: 0 !important;
            }
            .content-cell {
                padding: 30px 20px !important;
            }
            .header-cell {
                padding: 30px 20px !important;
            }
            .detail-label {
                width: 110px !important;
            }
        }
    </style>
</head>

<body style="margin: 0; padding: 0; font-family: 'Outfit', 'Segoe UI', Arial, sans-serif; background-color: #f1f5f9; color: #1e293b; -webkit-font-smoothing: antialiased;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f1f5f9; padding: 40px 10px;">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="container-table" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);">

                    <!-- Encabezado con colores pasteles suaves -->
                    <tr>
                        <td align="center" class="header-cell" style="background-color: #e0f2fe; padding: 45px 35px; border-bottom: 1px solid #bae6fd;">
                            <h1 style="color: #0369a1; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">
                                {{ $company?->name ?? config('mail.from.name') ?? 'Luxury Evys' }}
                            </h1>
                            <p style="color: #0284c7; margin: 6px 0 0 0; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px;">
                                Tecnicentro & Servicio Automotriz
                            </p>
                        </td>
                    </tr>

                    <!-- Cuerpo del correo -->
                    <tr>
                        <td class="content-cell" style="padding: 45px 35px; background-color: #ffffff;">
                            <h2 style="color: #0f172a; margin-top: 0; margin-bottom: 18px; font-weight: 700; font-size: 20px;">
                                ¡Hola, {{ $data['cliente'] }}!
                            </h2>

                            <p style="font-size: 15px; line-height: 1.6; color: #475569; font-weight: 400; margin-bottom: 25px;">
                                {{ $data['mensaje_principal'] }}
                            </p>

                            <!-- Cuadro de detalles con estilo moderno -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 30px 0; background-color: #f8fafc; border-left: 4px solid #38bdf8; border-radius: 0 12px 12px 0; padding: 22px;">
                                <tr>
                                    <td style="color: #475569; font-size: 14px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td class="detail-label" width="130" style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #0f172a; vertical-align: top; text-transform: uppercase; letter-spacing: 0.5px;">Vehículo:</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #334155; vertical-align: top;">{{ $data['vehiculo'] ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label" width="130" style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #0f172a; vertical-align: top; text-transform: uppercase; letter-spacing: 0.5px;">Placa:</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #334155; vertical-align: top;">{{ $data['placa'] ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label" width="130" style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #0f172a; vertical-align: top; text-transform: uppercase; letter-spacing: 0.5px;">Servicio/Acción:</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #334155; vertical-align: top;">{{ $data['accion'] }}</td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label" width="130" style="padding: 6px 0; font-size: 14px; font-weight: 700; color: #0f172a; vertical-align: top; text-transform: uppercase; letter-spacing: 0.5px;">Registro:</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #334155; vertical-align: top;">{{ date('d-m-Y H:i:s') }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 14px; color: #64748b; margin-bottom: 0; font-weight: 400; line-height: 1.5;">
                                Si tienes dudas o comentarios sobre este reporte, comunícate directamente con nuestro equipo técnico en el local.
                            </p>
                        </td>
                    </tr>

                    <!-- Pie de página suave -->
                    <tr>
                        <td align="center" style="background-color: #f8fafc; padding: 25px 20px; border-top: 1px solid #f1f5f9; color: #94a3b8; font-size: 12px; font-weight: 400;">
                            <p style="margin: 0; line-height: 1.5;">&copy; {{ date('Y') }} {{ $company?->name ?? config('mail.from.name') ?? 'Luxury Evys' }}. Todos los derechos reservados.</p>
                            <p style="margin: 4px 0 0 0; color: #cbd5e1; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Quito, Ecuador</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>