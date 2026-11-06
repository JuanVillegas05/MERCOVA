@php
    $rangeLabels = ['today' => 'Hoy', 'week' => 'Esta semana', 'month' => 'Este mes', 'all' => 'Todo el tiempo'];
    $statusColors = ['pending' => '#f59e0b', 'processing' => '#6366f1', 'completed' => '#10b981', 'cancelled' => '#ef4444'];
    $originColors = ['orders' => '#10b981', 'bookings' => '#6366f1', 'rentals' => '#f59e0b'];
    $maxGrowth = max(1, collect($snapshot['growth'])->flatMap(fn ($day) => [$day['new_users'], $day['new_orders']])->max());
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen y crecimiento · Mercova</title>
    <style>
        {{-- Dompdf no soporta Tailwind ni CSS externo: todo el estilo va
             inline aquí, con propiedades simples (sin flex/grid) para que
             se imprima igual de bien en PDF que en pantalla. --}}
        body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; font-size: 12px; }
        .header { border-bottom: 3px solid #059669; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { color: #059669; font-size: 22px; margin: 0; }
        .header p { color: #64748b; margin: 4px 0 0; font-size: 11px; }
        h2 { font-size: 15px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 26px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 6px 8px; font-size: 11px; }
        th { background-color: #f1f5f9; color: #334155; border-bottom: 1px solid #cbd5e1; }
        td { border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .cards { width: 100%; }
        .cards td { width: 25%; padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
        .cards .value { font-size: 17px; font-weight: bold; color: #0f172a; display: block; }
        .cards .label { font-size: 10px; color: #64748b; }
        .bar-row td { border-bottom: none; padding: 3px 8px; vertical-align: middle; }
        .bar-label { width: 70px; color: #64748b; font-size: 10px; }
        .bar-track { background-color: #f1f5f9; border-radius: 3px; }
        .bar-fill { height: 10px; border-radius: 3px; }
        .legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
        .footer { margin-top: 30px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Mercova · Resumen y crecimiento</h1>
        <p>Rango: {{ $rangeLabels[$snapshot['range']] ?? $snapshot['range'] }} · Generado el {{ $generatedAt->format('d/m/Y H:i') }}</p>
    </div>

    <table class="cards">
        <tr>
            <td><span class="value">${{ number_format($snapshot['summary']['revenue']) }}</span><span class="label">Dinero ingresado (COP)</span></td>
            <td><span class="value">{{ number_format($snapshot['summary']['customers']) }}</span><span class="label">Clientes activos</span></td>
            <td><span class="value">{{ number_format($snapshot['summary']['orders_count']) }}</span><span class="label">Pedidos</span></td>
            <td><span class="value">${{ number_format($snapshot['summary']['average_order_value']) }}</span><span class="label">Promedio por pedido</span></td>
        </tr>
    </table>

    <h2>Crecimiento diario · últimos {{ count($snapshot['growth']) }} días</h2>
    <table>
        @foreach ($snapshot['growth'] as $day)
            <tr class="bar-row">
                <td class="bar-label">{{ $day['label'] }}</td>
                <td style="width: 45%;">
                    <table class="bar-track"><tr><td style="border:none; padding:0;">
                        <div class="bar-fill" style="width: {{ max(2, round($day['new_users'] / $maxGrowth * 100)) }}%; background-color: #10b981;"></div>
                    </td></tr></table>
                </td>
                <td style="width: 30px;" class="text-right">{{ $day['new_users'] }}</td>
                <td style="width: 45%;">
                    <table class="bar-track"><tr><td style="border:none; padding:0;">
                        <div class="bar-fill" style="width: {{ max(2, round($day['new_orders'] / $maxGrowth * 100)) }}%; background-color: #818cf8;"></div>
                    </td></tr></table>
                </td>
                <td style="width: 30px;" class="text-right">{{ $day['new_orders'] }}</td>
            </tr>
        @endforeach
    </table>
    <p style="font-size: 10px; color: #64748b; margin-top: 6px;">
        <span class="legend-dot" style="background-color: #10b981;"></span> Usuarios nuevos &nbsp;&nbsp;
        <span class="legend-dot" style="background-color: #818cf8;"></span> Pedidos nuevos
    </p>

    <h2>Estado de los pedidos</h2>
    <table>
        <thead><tr><th>Estado</th><th class="text-right">Pedidos</th></tr></thead>
        <tbody>
            @foreach ($snapshot['order_status_breakdown'] as $status)
                <tr>
                    <td><span class="legend-dot" style="background-color: {{ $statusColors[$status['status']] ?? '#94a3b8' }};"></span>{{ $status['label'] }}</td>
                    <td class="text-right">{{ number_format($status['count']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Origen de las solicitudes</h2>
    <table>
        <thead><tr><th>Origen</th><th class="text-right">Solicitudes</th></tr></thead>
        <tbody>
            @foreach ($snapshot['request_origin_breakdown'] as $origin)
                <tr>
                    <td><span class="legend-dot" style="background-color: {{ $originColors[$origin['type']] ?? '#94a3b8' }};"></span>{{ $origin['label'] }}</td>
                    <td class="text-right">{{ number_format($origin['count']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Mercova · Reporte generado automáticamente desde el panel de administración.</div>
</body>
</html>
