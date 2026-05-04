@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ══ RESET & BASE ══ */
*, *::before, *::after { box-sizing: border-box; }
.kmd { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: #1a202c; }
.mono { font-family: 'JetBrains Mono', monospace; }
html.dark .kmd { color: #e2e8f0; }

/* ══ CARD ══ */
.card { background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; }
html.dark .card { background: #1e2433; border-color: #2d3748; }
.card-lift { transition: transform .2s ease, box-shadow .2s ease; }
.card-lift:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }

/* ══ KPI CARDS ══ */
.kpi-card { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; padding: 18px 20px; }
html.dark .kpi-card { background: #1e2433; border-color: #2d3748; }
.kpi-card .accent-bar { height: 3px; border-radius: 2px; margin-bottom: 16px; }
.kpi-card .kpi-val { font-family: 'JetBrains Mono', monospace; font-size: 22px; font-weight: 500; line-height: 1; }
.kpi-card .kpi-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: #64748b; margin-top: 6px; }
html.dark .kpi-card .kpi-label { color: #94a3b8; }
.kpi-card .kpi-sub { font-size: 11px; color: #94a3b8; margin-top: 3px; }

/* icon box */
.ic-box { width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
.ic-blue   { background: #dbeafe; color: #1d4ed8; }
.ic-red    { background: #fee2e2; color: #dc2626; }
.ic-green  { background: #dcfce7; color: #16a34a; }
.ic-amber  { background: #fef3c7; color: #d97706; }
.ic-purple { background: #ede9fe; color: #7c3aed; }
.ic-slate  { background: #f1f5f9; color: #475569; }
.ic-orange { background: #ffedd5; color: #ea580c; }
html.dark .ic-blue   { background: rgba(59,130,246,.15); color: #93c5fd; }
html.dark .ic-red    { background: rgba(239,68,68,.15);  color: #fca5a5; }
html.dark .ic-green  { background: rgba(34,197,94,.15);  color: #86efac; }
html.dark .ic-amber  { background: rgba(245,158,11,.15); color: #fcd34d; }
html.dark .ic-purple { background: rgba(139,92,246,.15); color: #c4b5fd; }
html.dark .ic-slate  { background: rgba(100,116,139,.12);color: #94a3b8; }
html.dark .ic-orange { background: rgba(234,88,12,.15);  color: #fdba74; }

/* ══ BADGES / PILLS ══ */
.pill {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 7px; border-radius: 999px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
    white-space: nowrap; border: 1px solid transparent;
}
.pill-up     { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
.pill-down   { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
.pill-stable { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
.pill-proj   { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
html.dark .pill-up     { background: rgba(239,68,68,.12);  color: #fca5a5; border-color: rgba(239,68,68,.25); }
html.dark .pill-down   { background: rgba(34,197,94,.12);  color: #86efac; border-color: rgba(34,197,94,.25); }
html.dark .pill-stable { background: rgba(100,116,139,.12);color: #94a3b8; border-color: rgba(100,116,139,.25); }
html.dark .pill-proj   { background: rgba(59,130,246,.12); color: #93c5fd; border-color: rgba(59,130,246,.25); }

/* ══ FILTER ══ */
.f-sel {
    display: block; width: 100%;
    border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: 12px; font-family: inherit; font-weight: 500;
    padding: 8px 28px 8px 10px; background: #fff; color: #0f172a;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2394a3b8' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 7px center; background-size: 14px;
    transition: border-color .15s;
}
.f-sel:focus { outline: none; border-color: #2563eb; }
html.dark .f-sel { background-color: #2d3748; border-color: #4a5568; color: #e2e8f0; }

/* ══ SECTION HEADER ══ */
.sec-head { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #2563eb; border-left: 3px solid #2563eb; padding-left: 8px; line-height: 1; }
.sec-head-orange { color: #ea580c; border-color: #ea580c; }
.sec-head-green  { color: #16a34a; border-color: #16a34a; }

/* ══ TABLE ══ */
.data-tbl { width: 100%; border-collapse: collapse; min-width: 900px; font-size: 12px; }
.data-tbl thead th {
    padding: 9px 13px; text-align: left;
    font-size: 9.5px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: #64748b;
    background: #f8fafc; border-bottom: 1px solid #e2e8f0; white-space: nowrap;
    position: sticky; top: 0; z-index: 2;
}
html.dark .data-tbl thead th { background: #1a202c; color: #4b5563; border-color: #2d3748; }
.data-tbl thead th.r { text-align: right; }
.data-tbl thead th.c { text-align: center; }
.th-sort { cursor: pointer; user-select: none; }
.th-sort:hover { color: #2563eb; }
.data-tbl tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .1s; }
html.dark .data-tbl tbody tr { border-color: #1a202c; }
.data-tbl tbody tr:hover { background: #f8fafc; }
html.dark .data-tbl tbody tr:hover { background: rgba(255,255,255,.02); }
.data-tbl td { padding: 9px 13px; color: #334155; vertical-align: middle; }
html.dark .data-tbl td { color: #cbd5e1; }
.td-r { text-align: right; }
.td-c { text-align: center; }

/* row tint */
tr.r-up   { background: rgba(254,242,242,.5); }
tr.r-down { background: rgba(240,253,244,.5); }
tr.r-proj { background: rgba(239,246,255,.5); }
html.dark tr.r-up   { background: rgba(239,68,68,.04); }
html.dark tr.r-down { background: rgba(34,197,94,.04); }
html.dark tr.r-proj { background: rgba(59,130,246,.04); }

/* column group borders */
.th-ytd, .td-ytd { border-left: 2px solid rgba(245,158,11,.25) !important; }
.th-yoy, .td-yoy { border-left: 2px solid rgba(34,197,94,.25) !important; }
.th-ihk, .td-ihk { border-left: 2px solid rgba(139,92,246,.25) !important; }
.th-fc,  .td-fc  { border-left: 2px solid rgba(234,88,12,.25) !important; }

/* th group row */
.th-grp th { padding: 4px 13px; font-size: 8.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; border-bottom: none; }

/* ══ SORT CHIPS ══ */
.sort-chip {
    font-size: 10px; padding: 4px 10px;
    border: 1px solid #e2e8f0; border-radius: 5px;
    background: #fff; color: #64748b; cursor: pointer;
    transition: all .15s; white-space: nowrap; font-family: inherit; font-weight: 600;
}
html.dark .sort-chip { background: #2d3748; border-color: #4a5568; color: #94a3b8; }
.sort-chip:hover { border-color: #2563eb; color: #2563eb; }
.sort-chip.active { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; }
html.dark .sort-chip.active { background: rgba(37,99,235,.15); color: #93c5fd; border-color: #3b82f6; }

/* ══ SEARCH ══ */
.tbl-search {
    width: 100%; border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: 12px; font-family: inherit; padding: 7px 10px 7px 30px;
    background: #fff; color: #0f172a; transition: border-color .15s;
}
html.dark .tbl-search { background: #2d3748; border-color: #4a5568; color: #e2e8f0; }
.tbl-search:focus { outline: none; border-color: #2563eb; }

/* ══ EXPORT DD ══ */
.exp-dd {
    position: absolute; right: 0; top: calc(100% + 6px); width: 178px;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,.1); z-index: 50; overflow: hidden;
}
html.dark .exp-dd { background: #1e2433; border-color: #2d3748; }
.exp-item {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 13px; font-size: 11px; font-weight: 500; color: #334155;
    text-decoration: none; transition: background .1s;
}
html.dark .exp-item { color: #cbd5e1; }
.exp-item:hover { background: #f8fafc; }
html.dark .exp-item:hover { background: #2d3748; }

/* ══ PAGINATION ══ */
.pg-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 30px; height: 30px; padding: 0 6px;
    font-size: 12px; font-weight: 500; border-radius: 5px;
    border: 1px solid #e2e8f0; background: #fff; color: #374151;
    cursor: pointer; transition: all .12s; text-decoration: none;
}
html.dark .pg-btn { background: #1e2433; color: #d1d5db; border-color: #374151; }
.pg-btn:hover:not(.pg-active):not(.pg-dis) { background: #f8fafc; }
.pg-active { background: #2563eb; color: #fff; border-color: #2563eb; font-weight: 700; cursor: default; }
.pg-dis { background: #f8fafc; color: #cbd5e1; cursor: not-allowed; border-color: #f1f5f9; }
html.dark .pg-dis { background: #1a202c; color: #4b5563; border-color: #1a202c; }

/* ══ MISC ══ */
.scrollbar-x::-webkit-scrollbar { width: 4px; height: 4px; }
.scrollbar-x::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
html.dark .scrollbar-x::-webkit-scrollbar-thumb { background: #4a5568; }

@keyframes fadeUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
.fade-up { animation: fadeUp .35s ease-out; }

/* note box */
.note-box { border-radius: 6px; padding: 10px 13px; font-size: 11px; line-height: 1.6; }
.note-yellow { background: #fffbeb; border: 1px solid #fde68a; border-left: 3px solid #f59e0b; color: #78350f; }
.note-blue   { background: #eff6ff; border: 1px solid #bfdbfe; border-left: 3px solid #2563eb; color: #1e40af; }
.note-slate  { background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; }
html.dark .note-yellow { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.2); color: #fcd34d; }
html.dark .note-blue   { background: rgba(37,99,235,.08);  border-color: rgba(37,99,235,.2);  color: #93c5fd; }
html.dark .note-slate  { background: #1a202c; border-color: #2d3748; color: #94a3b8; }

/* movers */
.mover-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; transition: background .1s; }
.mover-item:hover { background: rgba(248,250,252,.8); }
html.dark .mover-item:hover { background: rgba(255,255,255,.02); }
.mover-rank { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0; }
</style>

@php
    $nl = [1=>__('messages.bulan_januari'),2=>__('messages.bulan_februari'),3=>__('messages.bulan_maret'),4=>__('messages.bulan_april'),5=>__('messages.bulan_mei'),6=>__('messages.bulan_juni'),7=>__('messages.bulan_juli'),8=>__('messages.bulan_agustus'),9=>__('messages.bulan_september'),10=>__('messages.bulan_oktober'),11=>__('messages.bulan_november'),12=>__('messages.bulan_desember')];

    $tahunFilter     = $tahunFilter     ?? (int)date('Y');
    $bulanFilter     = $bulanFilter     ?? null;
    $analisis        = $analisis        ?? ['naik'=>0,'turun'=>0,'stabil'=>0,'inflasi'=>0,'deflasi'=>0];
    $tahunTersedia   = $tahunTersedia   ?? [(int)date('Y')];
    $daftarKomoditas = $daftarKomoditas ?? collect();
    $data            = $data            ?? new \Illuminate\Pagination\LengthAwarePaginator(collect(),0,20);
    $inflasiMtm      = $inflasiMtm      ?? 0;
    $inflasiYoy      = $inflasiYoy      ?? 0;
    $inflasiYtd      = $inflasiYtd      ?? 0;
    $sparkDataBulanan   = $sparkDataBulanan   ?? array_fill(0,13,0);
    $sparkDataTahunan   = $sparkDataTahunan   ?? array_fill(0,12,null);
    $sparkLabelsTahunan = $sparkLabelsTahunan ?? ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $yoySparkData    = $yoySparkData    ?? array_fill(0,12,null);
    $yoySparkLabels  = $yoySparkLabels  ?? ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $ihkForecast     = $ihkForecast     ?? [];

    $tglIni   = \Carbon\Carbon::create($tahunFilter, $bulanFilter ?? (int)date('m'), 1);
    $tglLalu  = $tglIni->copy()->subMonth();
    $tglDepan = $tglIni->copy()->addMonth();
    $lblLalu  = ($nl[$tglLalu->month]  ?? '') . ' ' . $tglLalu->year;
    $lblDepan = ($nl[$tglDepan->month] ?? '') . ' ' . $tglDepan->year;
    $lblIni   = $bulanFilter ? (($nl[$bulanFilter] ?? '') . ' ' . $tahunFilter) : 'Semua Bulan — ' . $tahunFilter;
    $lblLaluTahun = ($tahunFilter - 1);

    $sparkLabelsBulanan = [];
    for ($i = 12; $i >= 0; $i--) {
        $tgl = $tglIni->copy()->subMonths($i);
        $sparkLabelsBulanan[] = substr($nl[$tgl->month] ?? 'Bln', 0, 3) . " '" . substr($tgl->year, 2, 2);
    }

    $tahunNow       = (int)date('Y');
    $inflasi        = $analisis['inflasi'] ?? 0;
    $deflasi        = $analisis['deflasi'] ?? 0;
    $fcNaik         = $analisis['naik']    ?? 0;
    $fcTurun        = $analisis['turun']   ?? 0;
    $fcStabil       = $analisis['stabil']  ?? 0;
    $totalAn        = $fcNaik + $fcTurun + $fcStabil;
    $totalKomoditas = $data->total();

    $dominant   = ($inflasi > $deflasi) ? 'naik' : (($deflasi > $inflasi) ? 'turun' : 'stabil');
    $fcDominant = ($fcNaik  > $fcTurun) ? 'naik' : (($fcTurun  > $fcNaik)  ? 'turun' : 'stabil');

    $collection = $data->getCollection();
    $topNaik    = $collection->where('status_mom','inflasi')->sortByDesc('persen_mom')->take(5);
    $topTurun   = $collection->where('status_mom','deflasi')->sortBy('persen_mom')->take(5);
@endphp

<div class="kmd fade-up" style="padding: 24px 22px 60px; background: #f8fafc; min-height: 100vh;">

{{-- ══ 1. PAGE HEADER ══ --}}
<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; padding-bottom:18px; border-bottom:2px solid #2563eb;">
    <div style="display:flex; align-items:center; gap:14px;">
        <div style="background:#2563eb; border-radius:10px; padding:11px 13px; color:#fff; box-shadow:0 4px 12px rgba(37,99,235,.3);">
            <i class="fas fa-chart-line" style="font-size:18px;"></i>
        </div>
        <div>
            <p style="font-size:10px; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:#2563eb; margin:0 0 4px;">BPS — SIGMAPRO &nbsp;·&nbsp; Laporan Eksekutif</p>
            <h1 style="font-size:20px; font-weight:700; color:#0f172a; margin:0; line-height:1.2;">Monitoring Harga &amp; Proyeksi Komoditas</h1>
            <p style="font-size:11px; color:#94a3b8; margin:4px 0 0;">Sumber data: <code style="background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:10px;color:#475569;">price_data · price_forecasts · IHK/RH</code> — diperbarui otomatis setiap minggu</p>
        </div>
    </div>
    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:6px;">
        <span class="pill pill-proj"><i class="fas fa-calendar" style="font-size:8px;"></i> {{ $lblIni }}</span>
        @if($bulanFilter)
            <span class="pill {{ $inflasiMtm >= 0 ? 'pill-up' : 'pill-down' }}">MtM {{ ($inflasiMtm>=0?'+':'').number_format($inflasiMtm,2,',','.') }}%</span>
            <span class="pill {{ $inflasiYoy >= 0 ? 'pill-up' : 'pill-down' }}">YoY {{ ($inflasiYoy>=0?'+':'').number_format($inflasiYoy,2,',','.') }}%</span>
            <span class="pill {{ $inflasiYtd >= 0 ? 'pill-up' : 'pill-down' }}">YtD {{ ($inflasiYtd>=0?'+':'').number_format($inflasiYtd,2,',','.') }}%</span>
        @endif
    </div>
</div>

{{-- ══ 2. FILTER ══ --}}
<div class="card" style="padding:16px 20px; margin-bottom:18px;">
    <form action="{{ route('laporan.komoditas.index') }}" method="GET">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; align-items:end;">
            <div>
                <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:5px;">Komoditas</label>
                <select name="komoditas_id" class="f-sel">
                    <option value="">Semua Komoditas</option>
                    @foreach($daftarKomoditas as $k)
                        <option value="{{ $k->id }}" {{ request('komoditas_id') == $k->id ? 'selected' : '' }}>{{ $k->nama_komoditas }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:5px;">Tahun</label>
                <select name="tahun" class="f-sel">
                    @foreach($tahunTersedia as $t)
                        <option value="{{ $t }}" {{ (int)$tahunFilter===(int)$t?'selected':'' }}>{{ $t }}{{ (int)$t>$tahunNow?' (Forecast)':'' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:5px;">Bulan</label>
                <select name="bulan" class="f-sel">
                    <option value="" {{ !$bulanFilter?'selected':'' }}>Semua Bulan</option>
                    @foreach($nl as $num => $nama)
                        <option value="{{ $num }}" {{ $bulanFilter==$num?'selected':'' }}>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:5px;">Status Harga</label>
                <select name="status" class="f-sel">
                    <option value="">Semua Status</option>
                    <option value="naik"   {{ request('status')=='naik'   ?'selected':'' }}>Naik</option>
                    <option value="turun"  {{ request('status')=='turun'  ?'selected':'' }}>Turun</option>
                    <option value="stabil" {{ request('status')=='stabil' ?'selected':'' }}>Stabil</option>
                    <option value="proj"   {{ request('status')=='proj'   ?'selected':'' }}>Hanya Forecast</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px 14px;background:#2563eb;color:#fff;font-size:11px;font-weight:700;border:none;border-radius:6px;cursor:pointer;transition:background .15s;" onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                    <i class="fas fa-filter" style="font-size:10px;"></i> Terapkan
                </button>
                <a href="{{ route('laporan.komoditas.index') }}" style="display:flex;align-items:center;justify-content:center;padding:8px 12px;background:#f1f5f9;color:#64748b;border-radius:6px;text-decoration:none;font-size:11px;transition:background .15s;" title="Reset Filter">
                    <i class="fas fa-rotate-left" style="font-size:11px;"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ══ 3. KPI STRIP ══ --}}
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:18px;">
    <div class="kpi-card card-lift">
        <div class="accent-bar" style="background:#2563eb;"></div>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;">
            <div class="ic-box ic-blue"><i class="fas fa-cube"></i></div>
        </div>
        <div class="kpi-val" style="color:#0f172a;">{{ $totalKomoditas }}</div>
        <div class="kpi-label">Total Komoditas</div>
        <div class="kpi-sub">Terpantau sistem</div>
    </div>

    @if($bulanFilter)
    <div class="kpi-card card-lift">
        <div class="accent-bar" style="background:#dc2626;"></div>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;">
            <div class="ic-box ic-red"><i class="fas fa-arrow-trend-up"></i></div>
            <span style="font-size:9px;font-weight:700;background:#fee2e2;color:#991b1b;padding:2px 6px;border-radius:4px;text-transform:uppercase;">MtM</span>
        </div>
        <div class="kpi-val" style="color:#dc2626;">{{ $inflasi }}</div>
        <div class="kpi-label">Harga Naik Aktual</div>
        <div class="kpi-sub">{{ $lblLalu }} → {{ $lblIni }}</div>
    </div>
    <div class="kpi-card card-lift">
        <div class="accent-bar" style="background:#16a34a;"></div>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;">
            <div class="ic-box ic-green"><i class="fas fa-arrow-trend-down"></i></div>
            <span style="font-size:9px;font-weight:700;background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px;text-transform:uppercase;">MtM</span>
        </div>
        <div class="kpi-val" style="color:#16a34a;">{{ $deflasi }}</div>
        <div class="kpi-label">Harga Turun Aktual</div>
        <div class="kpi-sub">{{ $lblLalu }} → {{ $lblIni }}</div>
    </div>
    <div class="kpi-card card-lift">
        <div class="accent-bar" style="background:#d97706;"></div>
        <div style="margin-bottom:10px;"><div class="ic-box ic-amber"><i class="fas fa-percent"></i></div></div>
        <div class="kpi-val" style="color:{{ $inflasiMtm>=0?'#dc2626':'#16a34a' }};">{{ ($inflasiMtm>=0?'+':'').number_format($inflasiMtm,2,',','.') }}%</div>
        <div class="kpi-label">Rata-rata MtM</div>
        <div class="kpi-sub">Perubahan harga rata-rata</div>
    </div>
    <div class="kpi-card card-lift">
        <div class="accent-bar" style="background:#7c3aed;"></div>
        <div style="margin-bottom:10px;"><div class="ic-box ic-purple"><i class="fas fa-calendar-check"></i></div></div>
        <div class="kpi-val" style="color:{{ $inflasiYoy>=0?'#dc2626':'#16a34a' }};">{{ ($inflasiYoy>=0?'+':'').number_format($inflasiYoy,2,',','.') }}%</div>
        <div class="kpi-label">YoY vs {{ $lblLaluTahun }}</div>
        <div class="kpi-sub">Dibanding tahun lalu</div>
    </div>
    <div class="kpi-card card-lift">
        <div class="accent-bar" style="background:#475569;"></div>
        <div style="margin-bottom:10px;"><div class="ic-box ic-slate"><i class="fas fa-calendar-days"></i></div></div>
        <div class="kpi-val" style="color:{{ $inflasiYtd>=0?'#dc2626':'#16a34a' }};">{{ ($inflasiYtd>=0?'+':'').number_format($inflasiYtd,2,',','.') }}%</div>
        <div class="kpi-label">YtD {{ $tahunFilter }}</div>
        <div class="kpi-sub">Akumulasi awal tahun</div>
    </div>
    @else
    <div class="kpi-card card-lift">
        <div class="accent-bar" style="background:#d97706;"></div>
        <div style="margin-bottom:10px;"><div class="ic-box ic-amber"><i class="fas fa-chart-line"></i></div></div>
        <div class="kpi-val" style="color:{{ $inflasiYtd>=0?'#dc2626':'#16a34a' }};">{{ ($inflasiYtd>=0?'+':'').number_format($inflasiYtd,2,',','.') }}%</div>
        <div class="kpi-label">YtD {{ $tahunFilter }}</div>
        <div class="kpi-sub">Akumulasi awal tahun</div>
    </div>
    <div class="kpi-card card-lift">
        <div class="accent-bar" style="background:#7c3aed;"></div>
        <div style="margin-bottom:10px;"><div class="ic-box ic-purple"><i class="fas fa-calendar-check"></i></div></div>
        <div class="kpi-val" style="color:{{ $inflasiYoy>=0?'#dc2626':'#16a34a' }};">{{ ($inflasiYoy>=0?'+':'').number_format($inflasiYoy,2,',','.') }}%</div>
        <div class="kpi-label">YoY vs {{ $lblLaluTahun }}</div>
        <div class="kpi-sub">Dibanding tahun lalu</div>
    </div>
    @endif
</div>

{{-- ══ 4. ANALISIS + PROYEKSI ══ --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px;">

    {{-- Perubahan Aktual MtM --}}
    <div class="card" style="padding:22px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px;">
            <div>
                <p class="sec-head">Perubahan Harga Aktual (MtM)</p>
                <p style="font-size:11px;color:#94a3b8;margin:5px 0 0 11px;">{{ $lblLalu }} → <strong style="color:#475569;">{{ $lblIni }}</strong></p>
            </div>
            @if($dominant==='naik') <span class="pill pill-up"><i class="fas fa-arrow-up" style="font-size:8px;"></i> Tekanan Inflasi</span>
            @elseif($dominant==='turun') <span class="pill pill-down"><i class="fas fa-arrow-down" style="font-size:8px;"></i> Tekanan Deflasi</span>
            @else <span class="pill pill-stable">Harga Stabil</span>
            @endif
        </div>
        <div class="note-box note-yellow" style="margin-bottom:14px;">
            <i class="fas fa-triangle-exclamation" style="margin-right:4px;"></i>
            <strong>Metodologi:</strong> Naik/turun dihitung berdasarkan perubahan IHK per komoditas menggunakan bobot resmi BPS. Analisis ini mencakup <strong>21 komoditas</strong> terpilih dan <strong>bukan</strong> angka inflasi gabungan resmi BPS.
        </div>
        @if($bulanFilter)
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:14px;">
            <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:7px;padding:14px;text-align:center;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:26px;font-weight:500;color:#dc2626;line-height:1;">{{ $inflasi }}</div>
                <div style="font-size:10px;font-weight:700;color:#991b1b;margin-top:6px;">Harga Naik</div>
                <div style="font-size:10px;color:#fca5a5;margin-top:2px;">&gt; +0,5% MtM</div>
            </div>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:14px;text-align:center;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:26px;font-weight:500;color:#16a34a;line-height:1;">{{ $deflasi }}</div>
                <div style="font-size:10px;font-weight:700;color:#166534;margin-top:6px;">Harga Turun</div>
                <div style="font-size:10px;color:#86efac;margin-top:2px;">&gt; -0,5% MtM</div>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:14px;text-align:center;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:26px;font-weight:500;color:#475569;line-height:1;">{{ max(0,$totalKomoditas-$inflasi-$deflasi) }}</div>
                <div style="font-size:10px;font-weight:700;color:#334155;margin-top:6px;">Stabil</div>
                <div style="font-size:10px;color:#94a3b8;margin-top:2px;">±0,5% MtM</div>
            </div>
        </div>
        @if($totalKomoditas > 0)
        @php
            $pNaik   = round($inflasi/$totalKomoditas*100,1);
            $pTurun  = round($deflasi/$totalKomoditas*100,1);
            $pStabil = round(max(0,$totalKomoditas-$inflasi-$deflasi)/$totalKomoditas*100,1);
        @endphp
        <div style="margin-bottom:14px;">
            <div style="display:flex;border-radius:4px;overflow:hidden;height:8px;background:#f1f5f9;">
                @if($pNaik>0)<div style="width:{{ $pNaik }}%;background:#dc2626;"></div>@endif
                @if($pStabil>0)<div style="width:{{ $pStabil }}%;background:#e2e8f0;"></div>@endif
                @if($pTurun>0)<div style="width:{{ $pTurun }}%;background:#16a34a;"></div>@endif
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:4px;font-size:10px;color:#94a3b8;">
                <span style="color:#dc2626;font-weight:600;">{{ $pNaik }}% naik</span>
                <span>{{ $pStabil }}% stabil</span>
                <span style="color:#16a34a;font-weight:600;">{{ $pTurun }}% turun</span>
            </div>
        </div>
        @endif
        @if($totalAn > 0)
        <div class="note-box note-blue">
            <i class="fas fa-circle-info" style="margin-right:4px;"></i>
            @if($inflasi > $deflasi)
                {{ __('messages.kesimpulan_inflasi', ['inflasi'=>$inflasi,'deflasi'=>$deflasi,'naik'=>$analisis['naik'],'turun'=>$analisis['turun'],'stabil'=>$analisis['stabil'],'total'=>$totalAn,'bulan_ini'=>$lblIni,'bulan_depan'=>$lblDepan]) }}
            @elseif($deflasi > $inflasi)
                {{ __('messages.kesimpulan_deflasi', ['inflasi'=>$inflasi,'deflasi'=>$deflasi,'naik'=>$analisis['naik'],'turun'=>$analisis['turun'],'stabil'=>$analisis['stabil'],'total'=>$totalAn,'bulan_ini'=>$lblIni,'bulan_depan'=>$lblDepan]) }}
            @else
                {{ __('messages.kesimpulan_stabil', ['naik'=>$analisis['naik'],'turun'=>$analisis['turun'],'stabil'=>$analisis['stabil'],'total'=>$totalAn,'bulan_ini'=>$lblIni,'bulan_depan'=>$lblDepan]) }}
            @endif
        </div>
        @endif
        @else
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 0;color:#cbd5e1;">
            <i class="fas fa-calendar-xmark" style="font-size:28px;margin-bottom:10px;"></i>
            <p style="font-size:12px;color:#94a3b8;text-align:center;">Pilih bulan untuk melihat perbandingan MtM aktual</p>
        </div>
        @endif
    </div>

    {{-- Proyeksi Model --}}
    <div class="card" style="padding:22px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px;">
            <div>
                <p class="sec-head sec-head-orange">Proyeksi Model Forecast (Prophet)</p>
                <p style="font-size:11px;color:#94a3b8;margin:5px 0 0 11px;">{{ $lblIni }} → <strong style="color:#ea580c;">{{ $lblDepan }}</strong></p>
            </div>
            @if($fcDominant==='naik') <span class="pill pill-up"><i class="fas fa-robot" style="font-size:8px;"></i> Prediksi Naik</span>
            @elseif($fcDominant==='turun') <span class="pill pill-down"><i class="fas fa-robot" style="font-size:8px;"></i> Prediksi Turun</span>
            @else <span class="pill pill-stable"><i class="fas fa-robot" style="font-size:8px;"></i> Prediksi Stabil</span>
            @endif
        </div>
        <div class="note-box note-blue" style="margin-bottom:14px;">
            Proyeksi dihasilkan model <strong>Prophet</strong> dengan deteksi tren dan musiman otomatis.
            Arah <em>naik/turun</em> menunjukkan perkiraan harga <strong>{{ $lblDepan }}</strong> dibandingkan <strong>{{ $lblIni }}</strong>.
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:14px;">
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:14px;text-align:center;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:26px;font-weight:500;color:#16a34a;line-height:1;">{{ $fcNaik }}</div>
                <div style="font-size:10px;font-weight:700;color:#166534;margin-top:6px;">Prediksi Naik</div>
                <div style="font-size:10px;color:#86efac;margin-top:2px;">komoditas</div>
            </div>
            <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:7px;padding:14px;text-align:center;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:26px;font-weight:500;color:#dc2626;line-height:1;">{{ $fcTurun }}</div>
                <div style="font-size:10px;font-weight:700;color:#991b1b;margin-top:6px;">Prediksi Turun</div>
                <div style="font-size:10px;color:#fca5a5;margin-top:2px;">komoditas</div>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:14px;text-align:center;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:26px;font-weight:500;color:#475569;line-height:1;">{{ $fcStabil }}</div>
                <div style="font-size:10px;font-weight:700;color:#334155;margin-top:6px;">Stabil</div>
                <div style="font-size:10px;color:#94a3b8;margin-top:2px;">komoditas</div>
            </div>
        </div>
        @if($totalAn > 0)
        @php
            $fpNaik   = round($fcNaik/$totalAn*100,1);
            $fpTurun  = round($fcTurun/$totalAn*100,1);
            $fpStabil = round($fcStabil/$totalAn*100,1);
        @endphp
        <div style="margin-bottom:14px;">
            <div style="display:flex;border-radius:4px;overflow:hidden;height:8px;background:#f1f5f9;">
                @if($fpNaik>0)<div style="width:{{ $fpNaik }}%;background:#16a34a;"></div>@endif
                @if($fpStabil>0)<div style="width:{{ $fpStabil }}%;background:#e2e8f0;"></div>@endif
                @if($fpTurun>0)<div style="width:{{ $fpTurun }}%;background:#dc2626;"></div>@endif
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:4px;font-size:10px;color:#94a3b8;">
                <span style="color:#16a34a;font-weight:600;">{{ $fpNaik }}% naik</span>
                <span>{{ $fpStabil }}% stabil</span>
                <span style="color:#dc2626;font-weight:600;">{{ $fpTurun }}% turun</span>
            </div>
        </div>
        @endif
        <div class="note-box note-slate">
            <i class="fas fa-circle-info" style="color:#94a3b8;margin-right:4px;"></i>
            Dari <strong>{{ $totalAn }}</strong> komoditas yang dianalisis, model memproyeksikan
            <strong style="color:#16a34a;">{{ $fcNaik }}</strong> komoditas harganya naik,
            <strong style="color:#dc2626;">{{ $fcTurun }}</strong> turun, dan
            <strong>{{ $fcStabil }}</strong> relatif stabil pada <strong>{{ $lblDepan }}</strong>.
        </div>
    </div>
</div>

{{-- ══ 5. TOP MOVERS ══ --}}
@if($bulanFilter && ($topNaik->count() > 0 || $topTurun->count() > 0))
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px;">
    <div class="card" style="overflow:hidden;">
        <div style="padding:12px 16px;background:#fff5f5;border-bottom:1px solid #fecaca;display:flex;align-items:center;gap:10px;">
            <div class="ic-box ic-red"><i class="fas fa-arrow-trend-up"></i></div>
            <div>
                <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#991b1b;margin:0;">Top 5 Kenaikan MtM</p>
                <p style="font-size:10px;color:#fca5a5;margin:2px 0 0;">{{ $lblLalu }} → {{ $lblIni }}</p>
            </div>
        </div>
        @forelse($topNaik as $item)
        <div class="mover-item" style="{{ !$loop->last ? 'border-bottom:1px solid #fff5f5;' : '' }}">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="mover-rank" style="background:#fee2e2;color:#dc2626;">{{ $loop->iteration }}</div>
                <div>
                    <p style="font-size:12px;font-weight:600;color:#0f172a;margin:0;">{{ $item->nama_komoditas }}</p>
                    @if($item->harga_bulan_lalu && $item->harga_bulan_ini)
                    <p style="font-size:10px;color:#94a3b8;margin:2px 0 0;">
                        Rp {{ number_format($item->harga_bulan_lalu,0,',','.') }}
                        <i class="fas fa-arrow-right" style="font-size:7px;color:#cbd5e1;margin:0 3px;"></i>
                        Rp {{ number_format($item->harga_bulan_ini,0,',','.') }}
                    </p>
                    @endif
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:500;color:#dc2626;">+{{ number_format($item->persen_mom,2,',','.') }}%</div>
                @if($item->selisih_mom && $item->selisih_mom>0)
                <div style="font-size:10px;color:#fca5a5;">+Rp {{ number_format($item->selisih_mom,0,',','.') }}</div>
                @endif
            </div>
        </div>
        @empty
        <div style="padding:24px;text-align:center;font-size:12px;color:#94a3b8;">Tidak ada data kenaikan</div>
        @endforelse
    </div>
    <div class="card" style="overflow:hidden;">
        <div style="padding:12px 16px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:10px;">
            <div class="ic-box ic-green"><i class="fas fa-arrow-trend-down"></i></div>
            <div>
                <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#166534;margin:0;">Top 5 Penurunan MtM</p>
                <p style="font-size:10px;color:#86efac;margin:2px 0 0;">{{ $lblLalu }} → {{ $lblIni }}</p>
            </div>
        </div>
        @forelse($topTurun as $item)
        <div class="mover-item" style="{{ !$loop->last ? 'border-bottom:1px solid #f0fdf4;' : '' }}">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="mover-rank" style="background:#dcfce7;color:#16a34a;">{{ $loop->iteration }}</div>
                <div>
                    <p style="font-size:12px;font-weight:600;color:#0f172a;margin:0;">{{ $item->nama_komoditas }}</p>
                    @if($item->harga_bulan_lalu && $item->harga_bulan_ini)
                    <p style="font-size:10px;color:#94a3b8;margin:2px 0 0;">
                        Rp {{ number_format($item->harga_bulan_lalu,0,',','.') }}
                        <i class="fas fa-arrow-right" style="font-size:7px;color:#cbd5e1;margin:0 3px;"></i>
                        Rp {{ number_format($item->harga_bulan_ini,0,',','.') }}
                    </p>
                    @endif
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:500;color:#16a34a;">{{ number_format($item->persen_mom,2,',','.') }}%</div>
                @if($item->selisih_mom && $item->selisih_mom<0)
                <div style="font-size:10px;color:#86efac;">−Rp {{ number_format(abs($item->selisih_mom),0,',','.') }}</div>
                @endif
            </div>
        </div>
        @empty
        <div style="padding:24px;text-align:center;font-size:12px;color:#94a3b8;">Tidak ada data penurunan</div>
        @endforelse
    </div>
</div>
@endif

{{-- ══ 6. CHARTS ══ --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px;">
    <div class="card" style="padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
            <p class="sec-head">@if($bulanFilter) Tren Perubahan Harga (13 Bulan) @else Perubahan Harga Per Bulan — {{ $tahunFilter }} @endif</p>
            <span style="font-size:10px;font-family:'JetBrains Mono',monospace;color:#94a3b8;background:#f8fafc;padding:2px 8px;border-radius:4px;border:1px solid #e2e8f0;">MtM %</span>
        </div>
        <p style="font-size:11px;color:#94a3b8;margin:4px 0 16px 11px;">Rata-rata perubahan harga (%) seluruh komoditas vs bulan sebelumnya.</p>
        <div style="position:relative;height:220px;">
            @if($bulanFilter)
                <canvas id="chartMtm"></canvas>
            @else
                @php $adaData = collect($sparkDataTahunan)->filter(fn($v) => $v !== null)->isNotEmpty(); @endphp
                @if($adaData)
                    <canvas id="chartMtm"></canvas>
                @else
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#cbd5e1;gap:8px;">
                        <i class="fas fa-chart-line" style="font-size:24px;"></i>
                        <p style="font-size:11px;color:#94a3b8;">Belum ada data untuk tahun {{ $tahunFilter }}</p>
                    </div>
                @endif
            @endif
        </div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
            <p class="sec-head sec-head-green">Perubahan YoY Per Bulan — {{ $tahunFilter }}</p>
            <span style="font-size:10px;font-family:'JetBrains Mono',monospace;color:#94a3b8;background:#f8fafc;padding:2px 8px;border-radius:4px;border:1px solid #e2e8f0;">YoY %</span>
        </div>
        <p style="font-size:11px;color:#94a3b8;margin:4px 0 16px 11px;">Perbandingan rata-rata harga tiap bulan vs bulan yang sama tahun {{ $tahunFilter-1 }}.</p>
        <div style="position:relative;height:220px;">
            @php $adaYoy = collect($yoySparkData)->filter(fn($v) => $v !== null)->isNotEmpty(); @endphp
            @if($adaYoy)
                <canvas id="chartYoY"></canvas>
            @else
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#cbd5e1;gap:8px;">
                    <i class="fas fa-chart-bar" style="font-size:24px;"></i>
                    <p style="font-size:11px;color:#94a3b8;">Belum ada data YoY untuk tahun {{ $tahunFilter }}</p>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ══ 7. TABEL DETAIL ══ --}}
<div class="card" style="overflow:hidden;" x-data="{ exportOpen: false }">

    {{-- Topbar --}}
    <div style="padding:14px 20px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
        <div>
            <p class="sec-head">Detail Perbandingan Harga Komoditas</p>
            <p style="font-size:11px;color:#94a3b8;margin:5px 0 0 11px;">
                @if($bulanFilter)
                    Aktual: {{ $lblLalu }} → {{ $lblIni }}
                    <span style="margin:0 6px;color:#cbd5e1;">|</span>
                    Proyeksi Prophet: <span style="color:#ea580c;font-weight:600;">{{ $lblIni }}</span>
                    <span style="margin:0 6px;color:#cbd5e1;">|</span> YoY vs {{ $lblLaluTahun }}
                    <span style="margin:0 6px;color:#cbd5e1;">|</span> YtD {{ $tahunFilter }}
                @else
                    Rata-rata harga seluruh bulan — {{ $tahunFilter }}
                @endif
            </p>
        </div>
        <div style="position:relative;flex-shrink:0;">
            <button @click="exportOpen = !exportOpen" @click.outside="exportOpen = false"
                    style="display:flex;align-items:center;gap:6px;padding:7px 14px;background:#2563eb;color:#fff;font-size:11px;font-weight:700;border:none;border-radius:6px;cursor:pointer;transition:background .15s;"
                    onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                <i class="fas fa-download" style="font-size:10px;"></i> Ekspor
                <i class="fas fa-chevron-down" style="font-size:9px;transition:transform .2s;" :style="exportOpen ? 'transform:rotate(180deg)' : ''"></i>
            </button>
            <div x-show="exportOpen" x-transition class="exp-dd" style="display:none;">
                <a href="{{ route('laporan.komoditas.cetak', request()->all()) }}" target="_blank" class="exp-item">
                    <i class="fas fa-print" style="color:#64748b;width:13px;"></i> Cetak Laporan
                </a>
                <hr style="border:none;border-top:1px solid #f1f5f9;margin:2px 0;">
                <a href="{{ route('laporan.komoditas.pdf', request()->all()) }}" class="exp-item">
                    <i class="fas fa-file-pdf" style="color:#dc2626;width:13px;"></i> Unduh PDF
                </a>
                <a href="{{ route('laporan.komoditas.csv', request()->all()) }}" class="exp-item">
                    <i class="fas fa-file-csv" style="color:#16a34a;width:13px;"></i> Unduh CSV
                </a>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div style="padding:8px 20px;background:#fafafa;border-bottom:1px solid #f1f5f9;display:flex;flex-wrap:wrap;align-items:center;gap:16px;font-size:11px;color:#64748b;">
        <span style="display:flex;align-items:center;gap:5px;"><span style="width:11px;height:11px;border-radius:3px;background:rgba(254,242,242,.8);border:1px solid #fecaca;display:inline-block;"></span>Naik &gt;0,5%</span>
        <span style="display:flex;align-items:center;gap:5px;"><span style="width:11px;height:11px;border-radius:3px;background:rgba(240,253,244,.8);border:1px solid #bbf7d0;display:inline-block;"></span>Turun &gt;0,5%</span>
        <span style="display:flex;align-items:center;gap:5px;"><span style="width:11px;height:11px;border-radius:3px;background:rgba(239,246,255,.8);border:1px solid #bfdbfe;display:inline-block;"></span>Hanya Forecast</span>
        <span style="margin-left:auto;display:flex;align-items:center;gap:12px;color:#94a3b8;font-size:10px;">
            <span><span style="display:inline-block;width:12px;height:2px;background:rgba(245,158,11,.5);vertical-align:middle;margin-right:3px;"></span>YtD</span>
            <span><span style="display:inline-block;width:12px;height:2px;background:rgba(34,197,94,.5);vertical-align:middle;margin-right:3px;"></span>YoY</span>
            <span><span style="display:inline-block;width:12px;height:2px;background:rgba(139,92,246,.5);vertical-align:middle;margin-right:3px;"></span>IHK/RH</span>
            <span><span style="display:inline-block;width:12px;height:2px;background:rgba(234,88,12,.5);vertical-align:middle;margin-right:3px;"></span>Forecast Prophet</span>
        </span>
    </div>

    {{-- Search & Sort --}}
    <div style="padding:10px 20px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;border-bottom:1px solid #f1f5f9;">
        <div style="position:relative;width:260px;">
            <i class="fas fa-magnifying-glass" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:10px;pointer-events:none;"></i>
            <input type="text" id="tblSearch" class="tbl-search" placeholder="Cari komoditas..." oninput="filterTable(this.value)">
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-left:auto;">
            <button class="sort-chip active" onclick="sortTable(event,'nama')">A–Z</button>
            <button class="sort-chip" onclick="sortTable(event,'mom_desc')">MtM ↓</button>
            <button class="sort-chip" onclick="sortTable(event,'mom_asc')">MtM ↑</button>
            <button class="sort-chip" onclick="sortTable(event,'yoy_desc')">YoY ↓</button>
            <button class="sort-chip" onclick="sortTable(event,'harga_desc')">Harga ↓</button>
        </div>
    </div>

    {{-- Table --}}
    <div class="scrollbar-x" style="overflow-x:auto;">
        <table class="data-tbl" id="mainTable">
            <thead>
                <tr class="th-grp">
                    <th style="background:#f8fafc;"></th>
                    <th colspan="2" style="text-align:center;background:#eff6ff;color:#1e40af;border-bottom:1px solid #bfdbfe;">Harga Aktual</th>
                    @if($bulanFilter)
                    <th colspan="3" style="text-align:center;background:#f8fafc;color:#334155;border-left:1px solid #e2e8f0;">Perubahan MtM ({{ $lblLalu }} → {{ $lblIni }})</th>
                    <th colspan="2" style="text-align:center;background:#fffbeb;color:#78350f;border-left:2px solid rgba(245,158,11,.3);border-bottom:1px solid #fde68a;">YtD {{ $tahunFilter }}</th>
                    <th colspan="2" style="text-align:center;background:#f0fdf4;color:#166534;border-left:2px solid rgba(34,197,94,.3);border-bottom:1px solid #bbf7d0;">YoY vs {{ $lblLaluTahun }}</th>
                    @endif
                    <th colspan="2" style="text-align:center;background:#faf5ff;color:#5b21b6;border-left:2px solid rgba(139,92,246,.3);border-bottom:1px solid #e9d5ff;">Indeks IHK / RH</th>
                    <th colspan="4" style="text-align:center;background:#fff7ed;color:#9a3412;border-left:2px solid rgba(234,88,12,.3);border-bottom:1px solid #fed7aa;">Forecast Prophet — {{ $lblIni }}</th>
                </tr>
                <tr>
                    <th class="th-sort" onclick="sortTable(event,'nama')">Komoditas <i class="fas fa-sort" style="font-size:8px;opacity:.35;margin-left:2px;"></i></th>
                    <th class="r">Harga {{ $bulanFilter ? $lblIni : $tahunFilter }}</th>
                    <th class="r">Harga {{ $bulanFilter ? $lblLalu : ($tahunFilter-1) }}</th>
                    @if($bulanFilter)
                    <th class="r" style="border-left:1px solid #f1f5f9;">Selisih</th>
                    <th class="r th-sort" onclick="sortTable(event,'mom_desc')">% MtM <i class="fas fa-sort" style="font-size:8px;opacity:.35;"></i></th>
                    <th class="c">Status</th>
                    <th class="r th-ytd th-sort" onclick="sortTable(event,'ytd_desc')">% YtD</th>
                    <th class="c th-ytd">Status</th>
                    <th class="r th-yoy th-sort" onclick="sortTable(event,'yoy_desc')">% YoY</th>
                    <th class="c th-yoy">Status</th>
                    @endif
                    <th class="r th-ihk">RH</th>
                    <th class="r th-ihk">IHK</th>
                    <th class="r th-fc">Harga Prediksi</th>
                    <th class="r th-fc">IHK Forecast</th>
                    <th class="c th-fc">Tren Model</th>
                    <th class="c">Status Akhir</th>
                </tr>
            </thead>
            <tbody id="mainTbody">
            @forelse($data as $item)
                <tr class="row-item {{ $item->status_mom==='inflasi'?'r-up':($item->status_mom==='deflasi'?'r-down':($item->status_mom==='only-forecast'?'r-proj':'')) }}"
                    data-nama="{{ strtolower($item->nama_komoditas) }}"
                    data-mom="{{ $item->persen_mom ?? 0 }}"
                    data-yoy="{{ $item->persen_yoy ?? 0 }}"
                    data-ytd="{{ $item->persen_ytd ?? 0 }}"
                    data-harga="{{ $item->harga_bulan_ini ?? 0 }}">

                    <td><span style="font-size:12px;font-weight:600;color:#0f172a;">{{ $item->nama_komoditas }}</span></td>

                    <td class="td-r">
                        @if($item->harga_bulan_ini)
                            <span class="mono" style="font-size:12px;font-weight:500;color:#0f172a;">Rp {{ number_format($item->harga_bulan_ini,0,',','.') }}</span>
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>
                    <td class="td-r">
                        @if($item->harga_bulan_lalu)
                            <span class="mono" style="font-size:12px;color:#94a3b8;">Rp {{ number_format($item->harga_bulan_lalu,0,',','.') }}</span>
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>

                    @if($bulanFilter)
                    <td class="td-r" style="border-left:1px solid #f1f5f9;">
                        @if($item->selisih_mom !== null)
                            @if($item->selisih_mom > 0) <span class="mono" style="font-size:12px;font-weight:500;color:#dc2626;">+Rp {{ number_format($item->selisih_mom,0,',','.') }}</span>
                            @elseif($item->selisih_mom < 0) <span class="mono" style="font-size:12px;font-weight:500;color:#16a34a;">−Rp {{ number_format(abs($item->selisih_mom),0,',','.') }}</span>
                            @else <span class="mono" style="font-size:12px;color:#94a3b8;">Rp 0</span>
                            @endif
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>
                    <td class="td-r">
                        @if($item->persen_mom !== null)
                            @if($item->persen_mom > 0) <span class="mono" style="font-size:12px;font-weight:700;color:#dc2626;">+{{ number_format($item->persen_mom,2,',','.') }}%</span>
                            @elseif($item->persen_mom < 0) <span class="mono" style="font-size:12px;font-weight:700;color:#16a34a;">{{ number_format($item->persen_mom,2,',','.') }}%</span>
                            @else <span class="mono" style="font-size:12px;color:#94a3b8;">0,00%</span>
                            @endif
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>
                    <td class="td-c">
                        @switch($item->status_mom)
                            @case('inflasi')       <span class="pill pill-up"><i class="fas fa-arrow-up" style="font-size:7px;"></i> Naik</span> @break
                            @case('deflasi')       <span class="pill pill-down"><i class="fas fa-arrow-down" style="font-size:7px;"></i> Turun</span> @break
                            @case('stabil')        <span class="pill pill-stable">Stabil</span> @break
                            @case('only-forecast') <span class="pill pill-proj">Proyeksi</span> @break
                            @default               <span style="color:#cbd5e1;">—</span>
                        @endswitch
                    </td>
                    <td class="td-r td-ytd">
                        @if(isset($item->persen_ytd) && $item->persen_ytd !== null)
                            @if($item->persen_ytd > 0) <span class="mono" style="font-size:12px;font-weight:500;color:#dc2626;">+{{ number_format($item->persen_ytd,2,',','.') }}%</span>
                            @elseif($item->persen_ytd < 0) <span class="mono" style="font-size:12px;font-weight:500;color:#16a34a;">{{ number_format($item->persen_ytd,2,',','.') }}%</span>
                            @else <span class="mono" style="font-size:12px;color:#94a3b8;">0,00%</span>
                            @endif
                            @if(isset($item->harga_awal_tahun) && $item->harga_awal_tahun)
                            <span style="display:block;font-size:10px;color:#94a3b8;margin-top:2px;">Jan {{ $tahunFilter }}: Rp {{ number_format($item->harga_awal_tahun,0,',','.') }}</span>
                            @endif
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>
                    <td class="td-c td-ytd">
                        @if(isset($item->persen_ytd) && $item->persen_ytd !== null)
                            @if($item->persen_ytd > 0.5) <span class="pill pill-up">Naik</span>
                            @elseif($item->persen_ytd < -0.5) <span class="pill pill-down">Turun</span>
                            @else <span class="pill pill-stable">Stabil</span>
                            @endif
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>
                    <td class="td-r td-yoy">
                        @if(isset($item->persen_yoy) && $item->persen_yoy !== null)
                            @if($item->persen_yoy > 0) <span class="mono" style="font-size:12px;font-weight:500;color:#dc2626;">+{{ number_format($item->persen_yoy,2,',','.') }}%</span>
                            @elseif($item->persen_yoy < 0) <span class="mono" style="font-size:12px;font-weight:500;color:#16a34a;">{{ number_format($item->persen_yoy,2,',','.') }}%</span>
                            @else <span class="mono" style="font-size:12px;color:#94a3b8;">0,00%</span>
                            @endif
                            @if(isset($item->harga_tahun_lalu) && $item->harga_tahun_lalu)
                            <span style="display:block;font-size:10px;color:#94a3b8;margin-top:2px;">{{ $lblLaluTahun }}: Rp {{ number_format($item->harga_tahun_lalu,0,',','.') }}</span>
                            @endif
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>
                    <td class="td-c td-yoy">
                        @if(isset($item->persen_yoy) && $item->persen_yoy !== null)
                            @if($item->persen_yoy > 0.5) <span class="pill pill-up">Naik</span>
                            @elseif($item->persen_yoy < -0.5) <span class="pill pill-down">Turun</span>
                            @else <span class="pill pill-stable">Stabil</span>
                            @endif
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>
                    @endif

                    {{-- IHK / RH --}}
                    <td class="td-r td-ihk">
                        @if(isset($item->rh) && $item->rh !== null)
                            <span class="mono" style="font-size:12px;font-weight:500;color:#7c3aed;">{{ number_format($item->rh,2,',','.') }}</span>
                        @elseif($item->harga_bulan_ini && $item->harga_bulan_lalu && $item->harga_bulan_lalu > 0)
                            @php $rh = $item->harga_bulan_ini / $item->harga_bulan_lalu * 100; @endphp
                            <span class="mono" style="font-size:12px;font-weight:500;color:#7c3aed;">{{ number_format($rh,2,',','.') }}</span>
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>
                    <td class="td-r td-ihk">
                        @if(isset($item->ihk) && $item->ihk !== null)
                            <span class="mono" style="font-size:12px;font-weight:700;color:#7c3aed;">{{ number_format($item->ihk,2,',','.') }}</span>
                            @if(isset($item->ihk_change) && $item->ihk_change !== null)
                                @if($item->ihk_change > 0) <span style="display:block;font-size:10px;font-weight:600;color:#dc2626;">+{{ number_format($item->ihk_change,2,',','.') }}</span>
                                @elseif($item->ihk_change < 0) <span style="display:block;font-size:10px;font-weight:600;color:#16a34a;">{{ number_format($item->ihk_change,2,',','.') }}</span>
                                @endif
                            @endif
                        @else <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>

                    {{-- ══ HARGA PREDIKSI PROPHET (FIX 6) ══ --}}
                    <td class="td-r td-fc">
                        @if(isset($item->harga_prediksi) && $item->harga_prediksi !== null)
                            <span class="mono" style="font-size:12px;font-weight:700;color:#ea580c;">
                                Rp {{ number_format($item->harga_prediksi,0,',','.') }}
                            </span>
                            {{-- Tampilkan selisih prediksi vs aktual sebagai referensi --}}
                            @if($item->harga_bulan_lalu)
                                @php $selPred = $item->harga_prediksi - $item->harga_bulan_lalu; @endphp
                                <span style="display:block;font-size:10px;margin-top:2px;color:{{ $selPred > 0 ? '#dc2626' : ($selPred < 0 ? '#16a34a' : '#94a3b8') }};">
                                    {{ $selPred > 0 ? '+' : '' }}Rp {{ number_format($selPred,0,',','.') }}
                                </span>
                            @endif
                        @else
                            <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>

                    {{-- IHK Forecast (dari Flask) --}}
                    <td class="td-r td-fc">
                        @php $fcBulanDepan = $ihkForecast['bulan_depan'] ?? null; @endphp
                        @if($fcBulanDepan)
                            <span class="mono" style="font-size:12px;font-weight:700;color:#7c3aed;">
                                {{ number_format($fcBulanDepan['nilai_ihk_forecast'], 2, ',', '.') }}
                            </span>
                            <span style="display:block;font-size:10px;color:#94a3b8;margin-top:2px;">
                                {{ number_format($fcBulanDepan['ihk_lower'], 2, ',', '.') }}–{{ number_format($fcBulanDepan['ihk_upper'], 2, ',', '.') }}
                            </span>
                            @php $kd = $fcBulanDepan['kondisi_forecast'] ?? null; @endphp
                            <span style="display:block;font-size:10px;font-weight:600;margin-top:2px;color:{{ $kd==='inflasi'?'#dc2626':($kd==='deflasi'?'#16a34a':'#94a3b8') }};">
                                {{ ucfirst($kd ?? '-') }}
                            </span>
                        @else
                            <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>

                    {{-- ══ TREN MODEL (FIX 6) ══ --}}
                    <td class="td-c td-fc">
                        @if(isset($item->tren_model) && $item->tren_model !== null)
                            @if($item->tren_model === 'naik')
                                <span style="display:inline-flex;align-items:center;gap:4px;color:#16a34a;font-size:12px;font-weight:600;">
                                    <i class="fas fa-arrow-up" style="font-size:10px;"></i> Naik
                                </span>
                            @elseif($item->tren_model === 'turun')
                                <span style="display:inline-flex;align-items:center;gap:4px;color:#dc2626;font-size:12px;font-weight:600;">
                                    <i class="fas fa-arrow-down" style="font-size:10px;"></i> Turun
                                </span>
                            @else
                                <span style="display:inline-flex;align-items:center;gap:4px;color:#64748b;font-size:12px;font-weight:600;">
                                    <i class="fas fa-minus" style="font-size:10px;"></i> Stabil
                                </span>
                            @endif
                        @else
                            <span style="color:#cbd5e1;">—</span>
                        @endif
                    </td>

                    {{-- Status Akhir --}}
                    <td class="td-c">
                        @switch($item->status_mom)
                            @case('inflasi')       <span class="pill pill-up">Naik</span> @break
                            @case('deflasi')       <span class="pill pill-down">Turun</span> @break
                            @case('stabil')        <span class="pill pill-stable">Stabil</span> @break
                            @case('only-forecast') <span class="pill pill-proj">Proyeksi</span> @break
                            @default               <span style="color:#cbd5e1;">—</span>
                        @endswitch
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $bulanFilter ? 16 : 9 }}">
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 0;color:#cbd5e1;gap:10px;">
                            <i class="fas fa-box-open" style="font-size:32px;"></i>
                            <p style="font-size:13px;color:#94a3b8;">{{ __('messages.data_tidak_ditemukan') }}</p>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($data->hasPages())
    <div style="padding:12px 20px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid #e2e8f0;background:#f8fafc;">
        <span style="font-size:11px;color:#64748b;">Menampilkan {{ $data->firstItem() }}–{{ $data->lastItem() }} dari {{ $data->total() }} data</span>
        <div>{{ $data->appends(request()->all())->links() }}</div>
    </div>
    @endif
</div>

</div>{{-- end kmd --}}

{{-- ══ SCRIPTS ══ --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dark     = document.documentElement.classList.contains('dark');
    const gridClr  = dark ? 'rgba(255,255,255,.04)' : 'rgba(0,0,0,.04)';
    const lblClr   = dark ? '#4b5563' : '#94a3b8';
    const tickFont = { size: 10, family: "'Plus Jakarta Sans', sans-serif" };
    const monoFont = { size: 10, family: "'JetBrains Mono', monospace" };

    const tooltipBase = {
        backgroundColor: dark ? '#1e2433' : '#fff',
        titleColor:      dark ? '#f1f5f9' : '#0f172a',
        bodyColor:       dark ? '#94a3b8' : '#475569',
        borderColor:     dark ? '#374151' : '#e2e8f0',
        borderWidth: 1, padding: 10, boxPadding: 5,
        titleFont: { size: 11, weight: '600', family: "'Plus Jakarta Sans', sans-serif" },
        bodyFont:  { size: 11, family: "'JetBrains Mono', monospace" },
        cornerRadius: 6,
    };

    // ── MtM Line Chart ──────────────────────────────────────
    const ctxMtm = document.getElementById('chartMtm');
    if (ctxMtm) {
        @if($bulanFilter)
        const labsMtm = @json($sparkLabelsBulanan);
        const dataMtm = @json($sparkDataBulanan);
        @else
        const labsMtm = @json($sparkLabelsTahunan);
        const dataMtm = @json($sparkDataTahunan);
        @endif

        const ptColors = dataMtm.map(v => v === null ? '#94a3b8' : v > 0 ? '#dc2626' : v < 0 ? '#16a34a' : '#64748b');
        const ptSizes  = dataMtm.map(v => v === null ? 3 : Math.abs(v) > 1 ? 6 : 4);

        const ctx2d = ctxMtm.getContext('2d');
        const grad  = ctx2d.createLinearGradient(0, 0, 0, 220);
        grad.addColorStop(0, 'rgba(37,99,235,0.12)');
        grad.addColorStop(1, 'rgba(37,99,235,0.0)');

        new Chart(ctxMtm, {
            type: 'line',
            data: {
                labels: labsMtm,
                datasets: [{
                    label: '% MtM', data: dataMtm,
                    borderColor: '#2563eb', backgroundColor: grad,
                    borderWidth: 2, pointRadius: ptSizes,
                    pointBackgroundColor: ptColors, pointBorderColor: '#fff',
                    pointBorderWidth: 1.5, pointHoverRadius: 7, pointHoverBorderWidth: 2,
                    tension: 0.4, fill: true, spanGaps: true,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltipBase,
                        callbacks: {
                            title: ctx => ctx[0].label,
                            label: ctx => {
                                const v = ctx.parsed.y;
                                if (v === null) return '  Belum ada data';
                                return `  MtM: ${v > 0 ? '+' : ''}${v.toFixed(2).replace('.', ',')}%`;
                            },
                            afterLabel: ctx => {
                                const v = ctx.parsed.y;
                                if (v === null) return '';
                                return v > 0 ? '  ↑ Rata-rata naik' : v < 0 ? '  ↓ Rata-rata turun' : '  → Stabil';
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { color: gridClr }, ticks: { color: lblClr, font: tickFont, maxRotation: 40 } },
                    y: {
                        grid: { color: gridClr }, border: { dash: [3, 3] },
                        ticks: { color: lblClr, font: monoFont, callback: v => (v > 0 ? '+' : '') + v.toFixed(2).replace('.', ',') + '%' }
                    }
                }
            }
        });
    }

    // ── YoY Bar Chart ────────────────────────────────────────
    const ctxYoy = document.getElementById('chartYoY');
    if (ctxYoy) {
        const labsYoy = @json($yoySparkLabels);
        const dataYoy = @json($yoySparkData);

        const barBg  = dataYoy.map(v => v === null ? 'rgba(148,163,184,.3)' : v > 0 ? 'rgba(220,38,38,.75)' : v < 0 ? 'rgba(22,163,74,.75)' : 'rgba(100,116,139,.5)');
        const barBrd = dataYoy.map(v => v === null ? '#94a3b8' : v > 0 ? '#b91c1c' : v < 0 ? '#15803d' : '#475569');

        new Chart(ctxYoy, {
            type: 'bar',
            data: {
                labels: labsYoy,
                datasets: [{
                    label: '% YoY', data: dataYoy,
                    backgroundColor: barBg, borderColor: barBrd,
                    borderWidth: 1.5, borderRadius: 4, borderSkipped: false,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltipBase,
                        callbacks: {
                            label: ctx => {
                                const v = ctx.parsed.y;
                                if (v === null) return '  Belum ada data';
                                return `  YoY: ${v >= 0 ? '+' : ''}${v.toFixed(2).replace('.', ',')}%`;
                            },
                            afterLabel: ctx => {
                                const v = ctx.parsed.y;
                                if (v === null) return '';
                                return v > 0 ? '  ↑ Lebih mahal dari tahun lalu' : v < 0 ? '  ↓ Lebih murah dari tahun lalu' : '  → Sama dengan tahun lalu';
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: lblClr, font: tickFont } },
                    y: {
                        grid: { color: gridClr }, border: { dash: [3, 3] },
                        ticks: { color: lblClr, font: monoFont, callback: v => (v > 0 ? '+' : '') + v.toFixed(2).replace('.', ',') + '%' }
                    }
                }
            }
        });
    }
});

function filterTable(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#mainTbody .row-item').forEach(tr => {
        tr.style.display = (!q || tr.dataset.nama.includes(q)) ? '' : 'none';
    });
}

function sortTable(e, key) {
    document.querySelectorAll('.sort-chip').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');
    const tbody = document.getElementById('mainTbody');
    const rows  = Array.from(tbody.querySelectorAll('.row-item'));
    rows.sort((a, b) => {
        switch (key) {
            case 'nama':      return a.dataset.nama.localeCompare(b.dataset.nama);
            case 'mom_desc':  return parseFloat(b.dataset.mom)   - parseFloat(a.dataset.mom);
            case 'mom_asc':   return parseFloat(a.dataset.mom)   - parseFloat(b.dataset.mom);
            case 'yoy_desc':  return parseFloat(b.dataset.yoy)   - parseFloat(a.dataset.yoy);
            case 'ytd_desc':  return parseFloat(b.dataset.ytd)   - parseFloat(a.dataset.ytd);
            case 'harga_desc':return parseFloat(b.dataset.harga) - parseFloat(a.dataset.harga);
            default:          return 0;
        }
    });
    rows.forEach(r => tbody.appendChild(r));
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection