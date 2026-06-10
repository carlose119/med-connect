<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Receta Médica - {{ $prescription->unique_code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1a1a1a; padding: 40px; }
        .header { border-bottom: 3px solid #1e40af; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #1e40af; }
        .logo span { color: #10b981; }
        .rx-code { font-size: 18px; font-weight: bold; color: #1e40af; text-align: right; margin-top: -40px; }
        .rx-label { font-size: 10px; color: #6b7280; text-align: right; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .meta-box { border: 1px solid #e5e7eb; padding: 12px; width: 48%; }
        .meta-box h4 { color: #1e40af; font-size: 10px; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .meta-box p { margin-bottom: 4px; }
        .meta-box .name { font-size: 14px; font-weight: bold; }
        .meta-box .detail { color: #6b7280; font-size: 10px; }
        .section-title { background: #1e40af; color: white; padding: 8px 16px; font-size: 12px; font-weight: bold; margin-bottom: 16px; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f3f4f6; color: #1e40af; padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #1e40af; }
        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .item-name { font-weight: bold; }
        .item-dose { color: #1e40af; font-size: 11px; }
        .item-indications { color: #6b7280; font-size: 10px; }
        .notes { background: #fffbeb; border: 1px solid #f59e0b; padding: 12px; border-radius: 4px; margin-top: 20px; }
        .notes-title { color: #b45309; font-weight: bold; font-size: 10px; text-transform: uppercase; margin-bottom: 4px; }
        .footer { margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 16px; font-size: 9px; color: #6b7280; text-align: center; }
        .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; color: #f3f4f6; opacity: 0.5; pointer-events: none; z-index: -1; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    @if($prescription->status === 'cancelled')
        <div class="watermark">CANCELADA</div>
    @endif

    <div class="header">
        <div class="logo">Med<span>Connect</span></div>
        <div style="margin-top: 4px; font-size: 10px; color: #6b7280;">Sistema de Gestión Médica</div>
        <div class="rx-code">RX-{{ $prescription->unique_code }}</div>
        <div class="rx-label">RECETA MÉDICA DIGITAL</div>
    </div>

    <div class="meta">
        <div class="meta-box">
            <h4>Profesional</h4>
            <p class="name">{{ $doctor->user->name ?? 'N/A' }}</p>
            <p class="detail">Licencia: {{ $doctor->license_number ?? 'N/A' }}</p>
            <p class="detail">Especialidad: {{ $doctor->specialty->name ?? 'N/A' }}</p>
        </div>
        <div class="meta-box">
            <h4>Paciente</h4>
            <p class="name">{{ $patient->user->name ?? 'N/A' }}</p>
            <p class="detail">DNI: {{ $patient->identification_number ?? 'N/A' }}</p>
            <p class="detail">Teléfono: {{ $patient->phone ?? 'N/A' }}</p>
            <p class="detail">Fecha: {{ $issued_at }}</p>
            @if($prescription->status === 'cancelled')
                <p class="detail" style="color: #991b1b; font-weight: bold;">CANCELADA: {{ $prescription->cancellation_reason }}</p>
            @endif
        </div>
    </div>

    <div class="section-title">MEDICAMENTOS RECETADOS</div>

    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:30%">Medicamento</th>
                <th style="width:25%">Dosis</th>
                <th style="width:40%">Indicaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="item-name">{{ $item->medicine_name }}</td>
                <td class="item-dose">{{ $item->dosage }} — {{ $item->frequency }}<br>
                    <span style="font-size:9px; color:#6b7280;">Duración: {{ $item->duration }}</span>
                </td>
                <td class="item-indications">{{ $item->instructions }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($prescription->appointment?->notes)
    <div class="notes">
        <div class="notes-title">Notas del Profesional</div>
        <p>{{ $prescription->appointment->notes }}</p>
    </div>
    @endif

    <div class="footer">
        Documento generado por MedConnect — Verificable en {{ url('/') }}/api/prescriptions/{{ $prescription->id }}<br>
        Este documento es válido para dispensación farmacéutica. Conserve esta receta para sus registros.
    </div>
</body>
</html>