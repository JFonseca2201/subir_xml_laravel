<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de Mantenimiento Preventivo</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            margin: 0;
            padding: 20px 10px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        .email-container {
            max-width: 620px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 28px 20px;
            text-align: center;
            border-bottom: 3px solid #f59e0b;
        }
        .header-icon {
            display: inline-block;
            background: rgba(245, 158, 11, 0.18);
            border: 1px solid rgba(245, 158, 11, 0.4);
            border-radius: 50%;
            width: 48px;
            height: 48px;
            line-height: 48px;
            font-size: 22px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: 0.8px;
            color: #ffffff;
            text-transform: uppercase;
        }
        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #94a3b8;
            font-weight: 400;
        }
        .body {
            padding: 30px 24px;
        }
        .greeting {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 14px;
        }
        .main-text {
            font-size: 14px;
            color: #475569;
            margin-bottom: 20px;
        }
        .vehicle-card {
            background-color: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #f59e0b;
            padding: 18px 20px;
            margin: 22px 0;
        }
        .vehicle-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .detail-table td {
            padding: 6px 0;
            vertical-align: top;
        }
        .detail-label {
            color: #64748b;
            font-weight: 500;
            width: 45%;
        }
        .detail-value {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }
        .service-highlight {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 16px;
            margin: 18px 0;
            color: #92400e;
            font-size: 13px;
            font-weight: 600;
        }
        .notice-box {
            background-color: #f8fafc;
            border-left: 3px solid #0284c7;
            padding: 12px 14px;
            margin-bottom: 22px;
            font-size: 12.5px;
            color: #475569;
        }
        .cta-container {
            text-align: center;
            margin: 28px 0 10px;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
            letter-spacing: 0.3px;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        .footer strong {
            color: #334155;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Cabecera Institucional (Sin logos externos de frameworks) -->
        <div class="header">
            <div class="header-icon">🔧</div>
            <h1>{{ $companyName }}</h1>
            <p>Servicio Automotriz Especializado · Control de Mantenimiento Preventivo</p>
        </div>

        <div class="body">
            <div class="greeting">
                ¡Hola, {{ $clientName }}!
            </div>

            <p class="main-text">
                De acuerdo con nuestro registro técnico de control vehicular, tu vehículo ha completado el ciclo recomendado de <strong>{{ $intervalKm }} KM</strong> desde su último mantenimiento registrado, alcanzando aproximadamente los <strong>{{ $targetKm }} KM</strong>.
            </p>

            <!-- Ficha Técnica Resumen del Vehículo -->
            <div class="vehicle-card">
                <div class="vehicle-title">
                    📋 Detalle del Control Técnico
                </div>
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Vehículo:</td>
                        <td class="detail-value">{{ $vehicleModel }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Placa:</td>
                        <td class="detail-value">{{ $plate }}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Kilometraje Alcanzado:</td>
                        <td class="detail-value" style="color: #2563eb;">{{ $targetKm }} KM</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Ciclo Preventivo:</td>
                        <td class="detail-value">{{ $intervalKm }} KM cumplidos</td>
                    </tr>
                </table>
            </div>

            <!-- Servicio Sugerido -->
            <div class="service-highlight">
                👉 Servicio Correspondiente: <strong>{{ $serviceName }}</strong>
            </div>

            <div class="notice-box">
                ℹ️ <strong>¿Por qué es importante?</strong> Al alcanzar este recorrido, los fluidos y componentes de fricción completan su vida útil recomendada. Realizar su mantenimiento oportuno protege el motor, maximiza el rendimiento y garantiza tu seguridad.
            </div>

            @if(!empty($whatsappUrl))
                <div class="cta-container">
                    <a href="{{ $whatsappUrl }}" class="cta-button" target="_blank">
                        📲 Agendar Turno por WhatsApp
                    </a>
                </div>
            @endif
        </div>

        <!-- Pie Institucional -->
        <div class="footer">
            <p style="margin: 0 0 4px 0;">
                <strong>{{ $companyName }}</strong>
            </p>
            <p style="margin: 0 0 8px 0; font-size: 11.5px; color: #94a3b8;">
                {{ $companyAddress }} @if(!empty($companyPhone))· Tel: {{ $companyPhone }} @endif
            </p>
            <p style="margin: 0; font-size: 11px; color: #94a3b8;">
                Este es un mensaje automático de control y seguimiento técnico preventivo.
            </p>
        </div>
    </div>
</body>
</html>
