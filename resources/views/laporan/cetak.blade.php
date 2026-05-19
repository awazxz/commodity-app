@php
    $totalRows = $rows->count();
    $inflasi   = $analisis['inflasi'] ?? 0;
    $deflasi   = $analisis['deflasi'] ?? 0;
    $stabil    = max(0, $totalRows - $inflasi - $deflasi);
    $dominant  = $inflasi > $deflasi ? 'inflasi' : ($deflasi > $inflasi ? 'deflasi' : 'stabil');
    $pNaik     = $totalRows > 0 ? round($inflasi / $totalRows * 100) : 0;
    $pTurun    = $totalRows > 0 ? round($deflasi / $totalRows * 100) : 0;
    $pStabil   = max(0, 100 - $pNaik - $pTurun);
    $mtm       = $inflasiMtm ?? null;
    $yoy       = $inflasiYoy ?? null;
    $ytd       = $inflasiYtd ?? null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Komoditas - BPS Provinsi Riau</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9px; line-height: 1.4; color: #1a202c;
            padding: 12px 16px;
        }

        /* ── HEADER ── */
        .header { margin-bottom: 7px; padding-bottom: 6px; border-bottom: 1.5px solid #043277; }
        .header h1 { font-size: 11px; color: #043277; text-transform: uppercase; letter-spacing: .03em; }
        .header-sub { display: table; width: 100%; margin-top: 2px; }
        .header-sub .hl { display: table-cell; font-size: 8px; color: #64748b; }
        .header-sub .hr { display: table-cell; font-size: 8px; color: #64748b; text-align: right; }

        /* ── PERIODE + KPI ── */
        .top-wrap { display: table; width: 100%; margin-bottom: 7px; border-collapse: separate; border-spacing: 5px 0; }
        .period-cell { display: table-cell; vertical-align: middle; white-space: nowrap; width: 1%; }
        .period-box {
            background: #eff6ff; border: 0.5px solid #bfdbfe; border-left: 2px solid #043277;
            border-radius: 4px; padding: 5px 10px;
        }
        .period-box .lbl { font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }
        .period-box .val { font-size: 13px; font-weight: 700; color: #043277; margin-top: 1px; }

        .kpi-cell { display: table-cell; vertical-align: top; }
        .kpi-inner { display: table; width: 100%; border-collapse: separate; border-spacing: 4px 0; }
        .kpi-box {
            display: table-cell; vertical-align: top;
            border: 0.5px solid #e2e8f0; border-top: 2px solid #043277;
            border-radius: 4px; padding: 5px 7px; background: #fff;
            white-space: nowrap;
        }
        .kpi-box.red   { border-top-color: #be123c; }
        .kpi-box.green { border-top-color: #15803d; }
        .kpi-val { font-size: 15px; font-weight: 700; line-height: 1; }
        .kpi-lbl { font-size: 7px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-top: 2px; }
        .kpi-sub { font-size: 6.5px; color: #94a3b8; margin-top: 1px; }
        .c-red   { color: #be123c; }
        .c-green { color: #15803d; }
        .c-blue  { color: #043277; }
        .c-gray  { color: #475569; }

        /* ── INSIGHT ── */
        .insight-wrap { display: table; width: 100%; margin-bottom: 7px;
            background: #f8fafc; border-left: 2px solid #043277; border-radius: 0 4px 4px 0; }
        .insight-text-cell { display: table-cell; vertical-align: middle; padding: 6px 10px; font-size: 8.5px; color: #374151; line-height: 1.6; }
        .insight-text-cell strong { color: #1a202c; }
        .insight-bar-cell { display: table-cell; vertical-align: middle; padding: 6px 10px 6px 0; width: 120px; white-space: nowrap; }
        .bar-lbl { font-size: 7.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-bottom: 3px; }
        .bar-track { height: 5px; border-radius: 3px; background: #e2e8f0; overflow: hidden; }
        .bar-inner { height: 100%; display: table; width: 100%; }
        .bar-seg { display: table-cell; }
        .bar-seg-red   { background: #be123c; }
        .bar-seg-gray  { background: #cbd5e1; }
        .bar-seg-green { background: #15803d; }
        .bar-labels { display: table; width: 100%; margin-top: 2px; }
        .bar-labels span { display: table-cell; font-size: 7px; }

        /* ── TABLE ── */
        .section-title {
            font-size: 8px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .08em; color: #043277;
            border-left: 2px solid #043277; padding-left: 6px; margin-bottom: 5px;
        }
        table.data-table { width: 100%; border-collapse: collapse; }
        table.data-table th {
            background: #f1f5f9; border: 0.5px solid #cbd5e1;
            padding: 4px 5px; text-align: center;
            text-transform: uppercase; font-size: 7.5px;
            letter-spacing: .05em; color: #475569; white-space: nowrap;
        }
        table.data-table th.left { text-align: left; }
        table.data-table td { border: 0.5px solid #e2e8f0; padding: 3px 5px; font-size: 8.5px; }
        table.data-table tbody tr:nth-child(even) { background: #f8fafc; }
        .td-r { text-align: right; }
        .td-c { text-align: center; }
        .tag {
            display: inline; padding: 1px 5px; border-radius: 3px;
            font-size: 7.5px; font-weight: 600; border: 0.5px solid;
        }
        .tag-up { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
        .tag-dn { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .tag-st { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
        .tag-fc { background: #eff6ff; color: #043277; border-color: #bfdbfe; }

        /* ── FOOTNOTE ── */
        .footnote {
            margin-top: 7px; padding-top: 5px;
            border-top: 0.5px solid #e2e8f0;
            font-size: 7.5px; color: #94a3b8; line-height: 1.7;
        }
        .footnote strong { color: #64748b; }

        @media print {
            body { padding: 8px 12px; }
            @page { size: A4 landscape; margin: 0.5cm; }
        }
    </style>
</head>

<body onload="window.print()">

{{-- ══ HEADER ══ --}}
<div class="header">
    <h1>Badan Pusat Statistik Provinsi Riau &mdash; Monitoring Harga &amp; Proyeksi Komoditas</h1>
    <div class="header-sub">
        <span class="hl">Laporan Eksekutif SIGMAPRO &middot; Sumber: price_data &middot; price_forecasts &middot; IHK/RH</span>
        <span class="hr">Dicetak: {{ now()->isoFormat('D MMMM YYYY, HH:mm') }} WIB</span>
    </div>
</div>

{{-- ══ PERIODE + KPI ══ --}}
<div class="top-wrap">
    <div class="period-cell">
        <div class="period-box">
            <div class="lbl">Periode</div>
            <div class="val">{{ $tanggal ?? 'Semua Periode' }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <table class="kpi-inner">
            <tr>
                <td class="kpi-box">
                    <div class="kpi-val c-blue">{{ $totalRows }}</div>
                    <div class="kpi-lbl">Total Komoditas</div>
                    <div class="kpi-sub">Terpantau sistem</div>
                </td>
                <td class="kpi-box red">
                    <div class="kpi-val c-red">{{ $inflasi }}</div>
                    <div class="kpi-lbl">Harga Naik</div>
                    <div class="kpi-sub">&gt; +0,5% MtM</div>
                </td>
                <td class="kpi-box green">
                    <div class="kpi-val c-green">{{ $deflasi }}</div>
                    <div class="kpi-lbl">Harga Turun</div>
                    <div class="kpi-sub">&gt; -0,5% MtM</div>
                </td>
                <td class="kpi-box">
                    <div class="kpi-val c-gray">{{ $stabil }}</div>
                    <div class="kpi-lbl">Stabil</div>
                    <div class="kpi-sub">+-0,5% MtM</div>
                </td>
                @if($mtm !== null)
                <td class="kpi-box {{ $mtm >= 0 ? 'red' : 'green' }}">
                    <div class="kpi-val {{ $mtm >= 0 ? 'c-red' : 'c-green' }}">{{ ($mtm>=0?'+':'').number_format($mtm,2,',','.') }}%</div>
                    <div class="kpi-lbl">Rata-rata MtM</div>
                    <div class="kpi-sub">Perubahan rata-rata</div>
                </td>
                <td class="kpi-box {{ ($yoy??0) >= 0 ? 'red' : 'green' }}">
                    <div class="kpi-val {{ ($yoy??0) >= 0 ? 'c-red' : 'c-green' }}">{{ (($yoy??0)>=0?'+':'').number_format($yoy??0,2,',','.') }}%</div>
                    <div class="kpi-lbl">YoY</div>
                    <div class="kpi-sub">vs tahun lalu</div>
                </td>
                <td class="kpi-box {{ ($ytd??0) >= 0 ? 'red' : 'green' }}">
                    <div class="kpi-val {{ ($ytd??0) >= 0 ? 'c-red' : 'c-green' }}">{{ (($ytd??0)>=0?'+':'').number_format($ytd??0,2,',','.') }}%</div>
                    <div class="kpi-lbl">YtD</div>
                    <div class="kpi-sub">Akumulasi tahun ini</div>
                </td>
                @endif
            </tr>
        </table>
    </div>
</div>

{{-- ══ INSIGHT ══ --}}
<div class="insight-wrap">
    <div class="insight-text-cell">
        Periode <strong>{{ $tanggal ?? 'yang dipilih' }}</strong> &mdash;
        dari <strong>{{ $totalRows }} komoditas</strong> terpantau,
        @if($dominant === 'inflasi')
            <strong class="c-red">{{ $inflasi }} komoditas</strong> mengalami kenaikan harga dan <strong class="c-green">{{ $deflasi }}</strong> mengalami penurunan.
        @elseif($dominant === 'deflasi')
            <strong class="c-green">{{ $deflasi }} komoditas</strong> mengalami penurunan harga dan <strong class="c-red">{{ $inflasi }}</strong> mengalami kenaikan.
        @else
            sebagian besar komoditas dalam kondisi <strong>stabil</strong> (perubahan &lt;0,5% MtM).
        @endif
        @if($mtm !== null)
            Rata-rata MtM <strong class="{{ $mtm>=0?'c-red':'c-green' }}">{{ ($mtm>=0?'+':'').number_format($mtm,2,',','.') }}%</strong> &middot;
            YoY <strong class="{{ ($yoy??0)>=0?'c-red':'c-green' }}">{{ (($yoy??0)>=0?'+':'').number_format($yoy??0,2,',','.') }}%</strong> &middot;
            YtD <strong class="{{ ($ytd??0)>=0?'c-red':'c-green' }}">{{ (($ytd??0)>=0?'+':'').number_format($ytd??0,2,',','.') }}%</strong>.
        @endif
        @if($kondisiForecast ?? null)
            Proyeksi bulan berikutnya: <strong class="{{ $kondisiForecast==='inflasi'?'c-red':($kondisiForecast==='deflasi'?'c-green':'c-gray') }}">{{ ucfirst($kondisiForecast) }}</strong> (model Prophet IHK BPS 2022=100).
        @endif
    </div>
    <div class="insight-bar-cell">
        <div class="bar-lbl">Distribusi MtM</div>
        <div class="bar-track">
            <div class="bar-inner">
                @if($pNaik>0)<div class="bar-seg bar-seg-red"   style="width:{{ $pNaik }}%;display:table-cell;"></div>@endif
                @if($pStabil>0)<div class="bar-seg bar-seg-gray" style="width:{{ $pStabil }}%;display:table-cell;"></div>@endif
                @if($pTurun>0)<div class="bar-seg bar-seg-green" style="width:{{ $pTurun }}%;display:table-cell;"></div>@endif
            </div>
        </div>
        <table class="bar-labels" style="width:100%;">
            <tr>
                <td style="font-size:7px;" class="c-red">{{ $pNaik }}% naik</td>
                <td style="font-size:7px;text-align:right;" class="c-green">{{ $pTurun }}% turun</td>
            </tr>
        </table>
    </div>
</div>

{{-- ══ TABEL ══ --}}
<div class="section-title">Detail Data Komoditas</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:20px;">No</th>
            <th class="left">Komoditas</th>
            <th>Harga Bulan Lalu</th>
            <th>Harga Aktual</th>
            <th>Harga Prediksi</th>
            <th>Selisih MtM</th>
            <th>% MtM</th>
            <th>Tren</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $index => $item)
        @php
            $aktual  = (float)($item->harga_bulan_ini  ?? 0);
            $prediksi= (float)($item->harga_prediksi   ?? 0);
            $lalu    = (float)($item->harga_bulan_lalu ?? 0);
            $selisih = (float)($item->selisih_mom ?? ($aktual - $lalu));
            $persen  = isset($item->persen_mom) ? (float)$item->persen_mom : null;
            $status  = $item->status_mom  ?? '';
            $tren    = $item->tren_model  ?? null;
        @endphp
        <tr>
            <td class="td-c">{{ $index + 1 }}</td>
            <td>
                <strong>{{ $item->nama_komoditas }}</strong>
                @if(!empty($item->nama_varian))
                    <span style="color:#94a3b8;"> &middot; {{ $item->nama_varian }}</span>
                @endif
            </td>
            <td class="td-r">{{ $lalu>0 ? 'Rp '.number_format($lalu,0,',','.') : '-' }}</td>
            <td class="td-r">
                {{ $aktual>0 ? 'Rp '.number_format($aktual,0,',','.') : '-' }}
                @if($aktual==0 && isset($item->harga_bulan_ini_est) && $item->harga_bulan_ini_est > 0)
                    <span style="color:#94a3b8;font-size:7px;"> est.</span>
                @endif
            </td>
            <td class="td-r">{{ $prediksi>0 ? 'Rp '.number_format($prediksi,0,',','.') : '-' }}</td>
            <td class="td-r {{ $selisih>0?'c-red':($selisih<0?'c-green':'c-gray') }}">
                @if($selisih > 0)
                    + Rp {{ number_format($selisih,0,',','.') }}
                @elseif($selisih < 0)
                    - Rp {{ number_format(abs($selisih),0,',','.') }}
                @else
                    -
                @endif
            </td>
            <td class="td-r {{ ($persen??0)>0?'c-red':(($persen??0)<0?'c-green':'c-gray') }}">
                @if($persen !== null)
                    {{ ($persen>0?'+':'').number_format($persen,2,',','.').'%' }}
                @else
                    -
                @endif
            </td>
            <td class="td-c">
                @if($tren==='naik')       <span class="c-red">Naik</span>
                @elseif($tren==='turun')  <span class="c-green">Turun</span>
                @elseif($tren==='stabil') <span class="c-gray">Stabil</span>
                @else                     <span class="c-gray">-</span>
                @endif
            </td>
            <td class="td-c">
                @if($status==='inflasi')           <span class="tag tag-up">Inflasi</span>
                @elseif($status==='deflasi')       <span class="tag tag-dn">Deflasi</span>
                @elseif($status==='stabil')        <span class="tag tag-st">Stabil</span>
                @elseif($status==='only-forecast') <span class="tag tag-fc">Proyeksi</span>
                @else                              <span class="c-gray">-</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="9" class="td-c" style="padding:12px;color:#94a3b8;">Data tidak ditemukan.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ══ FOOTNOTE ══ --}}
<div class="footnote">
    <strong>Harga Bulan Lalu</strong> = harga aktual bulan sebelumnya (t-1) &nbsp;&middot;&nbsp;
    <strong>Harga Aktual</strong> = harga bulan berjalan (t); jika belum tersedia ditampilkan estimasi model (est.) &nbsp;&middot;&nbsp;
    <strong>Harga Prediksi</strong> = proyeksi harga bulan berikutnya (t+1) dari model Prophet BPS 2022=100.
    Laporan ini bersifat internal dan tidak menggantikan rilis resmi BPS.
</div>

</body>
</html>