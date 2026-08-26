<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura Electrónica</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            margin: 0;
            padding: 20px;
            line-height: 1.5;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .body {
            padding: 28px 24px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
        }
        .info-card {
            background-color: #f1f5f9;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
            border-left: 4px solid #0284c7;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        .info-value {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }
        .attachments-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 14px;
            margin: 20px 0;
            font-size: 13px;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 16px 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>{{ $sucursal->trade_name ?? $sucursal->name ?? 'LUXURY EVYS' }}</h1>
            <p>Comprobante Electrónico Autorizado por el SRI</p>
        </div>
        <div class="body">
            <div class="greeting">
                Estimado(a) {{ $sale->client->full_name ?? $sale->client->name ?? 'Cliente' }},
            </div>
            <p style="font-size: 14px; color: #475569;">
                Le informamos que se ha generado y autorizado su <strong>Factura Electrónica</strong> correspondiente a sus servicios / compras en nuestro establecimiento.
            </p>

            <div class="info-card">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tr>
                        <td style="padding: 4px 0; color: #64748b;">No. Factura:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #0f172a; text-align: right;">{{ $sale->document_number }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #64748b;">Fecha de Emisión:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #0f172a; text-align: right;">{{ $sale->service_date ? \Carbon\Carbon::parse($sale->service_date)->format('d/m/Y') : now()->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #64748b;">Importe Total:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #0284c7; text-align: right; font-size: 15px;">${{ number_format((float)$sale->total, 2) }}</td>
                    </tr>
                    @if(!empty($sale->sri_access_key))
                    <tr>
                        <td style="padding: 4px 0; color: #64748b; vertical-align: top;">Clave de Acceso:</td>
                        <td style="padding: 4px 0; font-family: monospace; font-size: 11px; color: #334155; text-align: right; word-break: break-all;">{{ $sale->sri_access_key }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            <div class="attachments-box">
                <strong>📎 Archivos adjuntos a este correo:</strong>
                <ul style="margin: 6px 0 0 16px; padding: 0; color: #475569;">
                    <li><strong>PDF (RIDE):</strong> Representación impresa de su factura oficial.</li>
                    <li><strong>XML:</strong> Archivo digital firmado y validado por el SRI.</li>
                </ul>
            </div>

            <p style="font-size: 12px; color: #64748b; margin-top: 16px;">
                Para cualquier consulta referente a este documento, puede contactarnos al correo <strong>{{ $sucursal->email }}</strong> o al teléfono <strong>{{ $sucursal->phone }}</strong>.
            </p>
        </div>
        <div class="footer">
            {{ $sucursal->trade_name ?? $sucursal->name }} &bull; {{ $sucursal->address }} &bull; RUC: {{ $sucursal->ruc }}<br>
            Este es un correo automático generado por el sistema de Facturación Electrónica.
        </div>
    </div>
</body>
</html>
