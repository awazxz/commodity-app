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
        table.data-table tbody tr:nth-child(even) { background-color: #f8fafc; }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .naik   { color: #be123c; font-weight: bold; }
        .turun  { color: #15803d; font-weight: bold; }
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
        <p>Tanggal Cetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td width="15%">Periode Laporan</td>
                <td width="2%">:</td>
                <td>{{ $tanggal ?? 'Semua Tanggal' }}</td>
            </tr>
            <tr>
                <td>Total Data</td>
                <td>:</td>
                {{-- FIX 1: ganti $data->count() → $rows->count() --}}
                <td><strong>{{ $rows->count() }} baris</strong></td>
            </tr>
            <tr>
                <td>Status Ringkasan</td>
                <td>:</td>
                <td>
                    <span class="naik">Naik: <strong>{{ $analisis['naik'] }}</strong></span> &nbsp;|&nbsp;
                    <span class="turun">Turun: <strong>{{ $analisis['turun'] }}</strong></span> &nbsp;|&nbsp;
                    <span class="stabil">Stabil: <strong>{{ $analisis['stabil'] }}</strong></span>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Komoditas &amp; Varian</th>
                <th>Harga Bulan Lalu</th>
                <th>Harga Aktual</th>
                <th>Harga Prediksi</th>
                <th>Selisih MoM</th>
                <th>%MoM</th>
                <th>Tren Model</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            {{-- FIX 2: ganti $data → $rows, dan pakai field yang sesuai object row dari controller --}}
            @forelse($rows as $index => $item)
            @php
                $aktual   = (float) ($item->harga_bulan_ini  ?? 0);
                $prediksi = (float) ($item->harga_prediksi   ?? 0);
                $lalu     = (float) ($item->harga_bulan_lalu ?? 0);
                $selisih  = $item->selisih_mom ?? ($aktual - $lalu);
                $persen   = $item->persen_mom  ?? null;
                $status   = $item->status_mom  ?? '';
                $tren     = $item->tren_model  ?? null;
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>

                <td>
                    {{ $item->nama_komoditas }}
                    @if($item->nama_varian)
                        <br><small style="color:#64748b;">({{ $item->nama_varian }})</small>
                    @endif
                </td>

                <td class="text-right">
                    {{ $lalu > 0 ? 'Rp ' . number_format($lalu, 0, ',', '.') : '-' }}
                </td>

                <td class="text-right">
                    {{ $aktual > 0 ? 'Rp ' . number_format($aktual, 0, ',', '.') : '-' }}
                </td>

                <td class="text-right">
                    {{ $prediksi > 0 ? 'Rp ' . number_format($prediksi, 0, ',', '.') : '-' }}
                </td>

                <td class="text-right {{ $selisih > 0 ? 'naik' : ($selisih < 0 ? 'turun' : 'stabil') }}">
                    {{ $selisih != 0 ? ($selisih > 0 ? '+' : '-') . ' Rp ' . number_format(abs($selisih), 0, ',', '.') : '-' }}
                </td>

                <td class="text-right {{ ($persen ?? 0) > 0 ? 'naik' : (($persen ?? 0) < 0 ? 'turun' : 'stabil') }}">
                    {{ $persen !== null ? ($persen > 0 ? '+' : '') . number_format($persen, 2, ',', '.') . '%' : '-' }}
                </td>

                <td class="text-center">
                    @if($tren === 'naik')
                        <span class="naik">▲ Naik</span>
                    @elseif($tren === 'turun')
                        <span class="turun">▼ Turun</span>
                    @elseif($tren === 'stabil')
                        <span class="stabil">— Stabil</span>
                    @else
                        <span class="stabil">-</span>
                    @endif
                </td>

                <td class="text-center">
                    @if($status === 'inflasi')
                        <span class="naik">Inflasi</span>
                    @elseif($status === 'deflasi')
                        <span class="turun">Deflasi</span>
                    @elseif($status === 'stabil')
                        <span class="stabil">Stabil</span>
                    @elseif($status === 'only-forecast')
                        <span style="color:#7c3aed;">Proyeksi</span>
                    @else
                        <span class="stabil">-</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 20px; color: #94a3b8;">
                    Data tidak ditemukan untuk parameter ini.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <strong>Kesimpulan Analisis:</strong>
        {{-- FIX 3: $analisis['kesimpulan'] tidak ada, diganti ringkasan dari data yang tersedia --}}
        <div class="summary-box">
            Berdasarkan data periode <strong>{{ $tanggal ?? 'yang dipilih' }}</strong>,
            terdapat <strong>{{ $analisis['naik'] }}</strong> komoditas dengan proyeksi harga naik,
            <strong>{{ $analisis['turun'] }}</strong> komoditas turun, dan
            <strong>{{ $analisis['stabil'] }}</strong> komoditas stabil.
            @if(($analisis['inflasi'] ?? 0) > 0 || ($analisis['deflasi'] ?? 0) > 0)
                Secara aktual, <strong>{{ $analisis['inflasi'] ?? 0 }}</strong> komoditas mengalami inflasi
                dan <strong>{{ $analisis['deflasi'] ?? 0 }}</strong> komoditas mengalami deflasi.
            @endif
        </div>
    </div>

</body>
</html>