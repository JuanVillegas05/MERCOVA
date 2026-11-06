<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de métricas · Mercova</title>
    <style>
        {{-- Dompdf no soporta Tailwind ni CSS externo: todo el estilo va
             inline aquí, con propiedades simples (sin flex/grid) para que
             se imprima igual de bien en PDF que en pantalla. --}}
        body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; font-size: 12px; }
        .header { border-bottom: 3px solid #059669; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { color: #059669; font-size: 22px; margin: 0; }
        .header p { color: #64748b; margin: 4px 0 0; font-size: 11px; }
        h2 { font-size: 15px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 28px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 6px 8px; font-size: 11px; }
        th { background-color: #f1f5f9; color: #334155; border-bottom: 1px solid #cbd5e1; }
        td { border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .summary-grid td.label { color: #64748b; width: 60%; }
        .summary-grid td.value { font-weight: bold; text-align: right; }
        .footer { margin-top: 30px; font-size: 9px; color: #94a3b8; text-align: center; }
        .status-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Mercova · Reporte de métricas</h1>
        <p>Rango: {{ $report['range']['from']->format('d/m/Y') }} al {{ $report['range']['to']->format('d/m/Y') }} · Generado el {{ $generatedAt->format('d/m/Y H:i') }}</p>
    </div>

    <h2>Resumen ejecutivo</h2>
    <table class="summary-grid">
        <tr><td class="label">Usuarios registrados</td><td class="value">{{ number_format($report['summary']['total_users']) }}</td></tr>
        <tr><td class="label">Emprendimientos activos</td><td class="value">{{ number_format($report['summary']['active_businesses']) }}</td></tr>
        <tr><td class="label">Publicaciones en venta</td><td class="value">{{ number_format($report['summary']['listings_sale']) }}</td></tr>
        <tr><td class="label">Publicaciones en alquiler</td><td class="value">{{ number_format($report['summary']['listings_rent']) }}</td></tr>
        <tr><td class="label">Servicios publicados</td><td class="value">{{ number_format($report['summary']['services']) }}</td></tr>
        <tr><td class="label">Transacciones en el rango</td><td class="value">{{ number_format($report['summary']['transactions_in_range']) }}</td></tr>
    </table>

    <h2>Desglose financiero</h2>
    <table class="summary-grid">
        <tr><td class="label">Ingresos totales</td><td class="value">${{ number_format($report['financial']['total_revenue']) }} COP</td></tr>
        <tr><td class="label">Comisiones de Mercova</td><td class="value">${{ number_format($report['financial']['total_commission']) }} COP</td></tr>
        <tr><td class="label">Monto neto a vendedores</td><td class="value">${{ number_format($report['financial']['net_amount']) }} COP</td></tr>
    </table>

    <h2>Ventas por mes</h2>
    <table>
        <thead><tr><th>Mes</th><th class="text-right">Transacciones</th><th class="text-right">Ingresos (COP)</th></tr></thead>
        <tbody>
            @forelse ($report['financial']['by_month'] as $row)
                <tr>
                    <td>{{ $row['period'] }}</td>
                    <td class="text-right">{{ number_format($row['transactions']) }}</td>
                    <td class="text-right">${{ number_format($row['revenue']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Sin transacciones en el rango seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Categorías más vendidas</h2>
    <table>
        <thead><tr><th>Categoría</th><th class="text-right">Unidades</th><th class="text-right">Ingresos (COP)</th></tr></thead>
        <tbody>
            @forelse ($report['performance']['top_categories'] as $category)
                <tr>
                    <td>{{ $category['name'] }}</td>
                    <td class="text-right">{{ number_format($category['units']) }}</td>
                    <td class="text-right">${{ number_format($category['revenue']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Sin ventas en el rango seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Estado de las órdenes · Tasa de ocupación de alquiler: {{ $report['performance']['rental_occupancy_rate'] }}%</h2>
    <table>
        <thead><tr><th>Estado</th><th class="text-right">Pedidos</th></tr></thead>
        <tbody>
            @foreach ($report['performance']['order_status_breakdown'] as $status)
                <tr>
                    <td>{{ $status['label'] }}</td>
                    <td class="text-right">{{ number_format($status['count']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Mercova · Reporte generado automáticamente desde el panel de administración.</div>
</body>
</html>
