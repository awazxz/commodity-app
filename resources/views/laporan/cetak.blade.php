<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Komoditas - BPS Provinsi Riau</title>
    <style>
        body { font-family: 'Inter', Helvetica, Arial, sans-serif; font-size: 11px; line-height: 1.5; color: #333; margin: 30px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #043277; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #043277; text-transform: uppercase; font-size: 16px; }
        .header p { margin: 5px 0; font-size: 10px; color: #666; }
        
        .info-section { margin-bottom: 20px; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 3px 0; vertical-align: top; }

        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th { background-color: #f8fafc; border: 1px solid #cbd5e1; padding: 8px; text-align: center; text-transform: uppercase; font-size: 9px; }
        table.data-table td { border: 1px solid #cbd5e1; padding: 8px; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 9px; }
        .naik { color: #be123c; }
        .turun { color: #15803d; }
        .stabil { color: #475569; }

        .footer { margin-top: 40px; }
        .summary-box { background: #f1f5f9; padding: 15px; border-radius: 8px; margin-top: 10px; }
        
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h2>Badan Pusat Statistik Provinsi Riau</h2>
        <p>Laporan Analisis Harga Aktual vs Prediksi Harian Komoditas</p>
        <p>Tanggal Cetak: {{ date('d/m/Y H:i') }}</p>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td width="15%">Periode Laporan</td>
                <td width="2%">:</td>
                <td>{{ $tanggal ?? 'Semua Tanggal' }}</td>
            </tr>
            <tr>
                <td>Status Ringkasan</td>
                <td>:</td>
                <td>
                    Naik: <strong>{{ $analisis['naik'] }}</strong> | 
                    Turun: <strong>{{ $analisis['turun'] }}</strong> | 
                    Stabil: <strong>{{ $analisis['stabil'] }}</strong>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Komoditas & Varian</th>
                <th>Harga Aktual</th>
                <th>Harga Prediksi</th>
                <th>Selisih</th>
                <th>Tren</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $index => $item)
            @php 
                $selisih = ($item->harga_prediksi ?? 0) - ($item->harga_aktual ?? 0);
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ \Carbon\Carbon::parse($item->tanggal)->format('d/m/Y') }}</td>
                <td>{{ $item->nama_komoditas }} ({{ $item->nama_varian }})</td>
                <td class="text-right">Rp {{ number_format($item->harga_aktual, 0, ',', '.') }}</td>
                <td class="text-right">{{ $item->harga_prediksi ? 'Rp ' . number_format($item->harga_prediksi, 0, ',', '.') : '-' }}</td>
                <td class="text-right {{ $selisih > 0 ? 'naik' : ($selisih < 0 ? 'turun' : '') }}">
                    {{ $selisih != 0 ? number_format($selisih, 0, ',', '.') : '-' }}
                </td>
                <td class="text-center">
                    @if($item->harga_prediksi > $item->harga_aktual)
                        <span class="naik">▲ Naik</span>
                    @elseif($item->harga_prediksi < $item->harga_aktual && $item->harga_prediksi > 0)
                        <span class="turun">▼ Turun</span>
                    @else
                        <span class="stabil"> Stabil</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">Data tidak ditemukan untuk parameter ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <strong>Kesimpulan Analisis:</strong>
        <div class="summary-box">
            {{ $analisis['kesimpulan'] }}
        </div>
    </div>

</body>
</html>