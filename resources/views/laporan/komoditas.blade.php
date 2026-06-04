@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ══ RESET & BASE ══ */
*, *::before, *::after { box-sizing: border-box; }
.kmd { font-family: 'Inter', sans-serif; font-size: 15px; color: #1a202c; }
.mono { font-family: 'Inter', monospace; font-variant-numeric: tabular-nums; }
html.dark .kmd { color: #e2e8f0; }

/* ══ LAYOUT ══ */
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* ══ CARD ══ */
.card {
    background: #ffffff;
    border-radius: 8px;
    border: 0.5px solid #e2e8f0;
    padding: 18px 20px;
}
html.dark .card { background: #1e2433; border-color: #2d3748; }

/* card dengan stretch vertikal */
.card-stretch {
    display: flex;
    flex-direction: column;
}
.card-stretch .spacer { flex: 1; }

/* ══ SECTION LABEL ══ */
.sec {
    font-size: 11px; font-weight: 600;
    letter-spacing: .12em; text-transform: uppercase;
    color: #1a56db; border-left: 2px solid #1a56db;
    padding-left: 7px; line-height: 1.3; margin-bottom: 3px;
}
.sec.gray { color: #4b5563; border-color: #4b5563; }
html.dark .sec { color: #93c5fd; border-color: #3b82f6; }
html.dark .sec.gray { color: #9ca3af; border-color: #6b7280; }

.sec-sub {
    font-size: 13px; color: #6b7280;
    margin: 3px 0 14px 9px;
}
html.dark .sec-sub { color: #9ca3af; }

/* ══ NOTE ══ */
.note {
    font-size: 13px; line-height: 1.65;
    padding: 9px 12px; border-radius: 6px;
    margin-bottom: 12px;
    border: 0.5px solid #e2e8f0;
    background: #cbd5e1; color: #4b5563;
}
.note.info {
    border-left: 2px solid #1a56db;
    background: #eff6ff; color: #1a3fa0;
    border-color: #bfdbfe;
}
html.dark .note { background: #1a202c; border-color: #2d3748; color: #9ca3af; }
html.dark .note.info { background: rgba(37,99,235,.08); border-color: rgba(37,99,235,.25); color: #93c5fd; }

/* ══ STAT BOXES ══ */
.stat3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 12px; }
.sbox {
    border: 0.5px solid #e2e8f0; border-radius: 6px;
    padding: 12px 8px; text-align: center;
    background: #f8fafc;
}
html.dark .sbox { background: #1a202c; border-color: #2d3748; }
.sbox-n { font-family: 'Inter', monospace; font-size: 22px; font-weight: 500; line-height: 1; }
.sbox-l { font-size: 12px; color: #6b7280; margin-top: 5px; font-weight: 500; }
.sbox-s { font-size: 11px; color: #9ca3af; margin-top: 2px; }
html.dark .sbox-l { color: #9ca3af; }

/* ══ BAR ══ */
.bar-wrap { margin-bottom: 12px; }
.bar-track { height: 3px; border-radius: 2px; background: #e2e8f0; overflow: hidden; display: flex; }
html.dark .bar-track { background: #2d3748; }
.bar-pct { display: flex; justify-content: space-between; margin-top: 5px; font-size: 12px; color: #6b7280; }

/* ══ KV GRID ══ */
.kv2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
.kv3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 12px; }
.kvbox {
    border: 0.5px solid #e2e8f0; border-radius: 6px;
    padding: 12px; background: #f8fafc;
}
html.dark .kvbox { background: #1a202c; border-color: #2d3748; }
.kvbox-l {
    font-size: 11px; font-weight: 600; letter-spacing: .07em;
    text-transform: uppercase; color: #6b7280; margin-bottom: 8px;
}
html.dark .kvbox-l { color: #9ca3af; }
.kvbox-v { font-family: 'Inter', monospace; font-size: 18px; font-weight: 500; line-height: 1; }
.kvbox-s { font-size: 12px; color: #9ca3af; margin-top: 4px; }

/* ══ IHK ROW ══ */
.ihk-row {
    display: flex; justify-content: space-between; align-items: center;
    border: 0.5px solid #e2e8f0; border-radius: 6px;
    padding: 12px 14px; margin-bottom: 12px; background: #f8fafc;
}
html.dark .ihk-row { background: #1a202c; border-color: #2d3748; }
.ihk-l { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
.ihk-v { font-family: 'Inter', monospace; font-size: 22px; font-weight: 500; color: #1a56db; }
.ihk-int { font-size: 12px; color: #9ca3af; margin-top: 3px; }
html.dark .ihk-v { color: #93c5fd; }

/* ══ PILL ══ */
.pill {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 4px 10px; border-radius: 99px;
    font-size: 12px; font-weight: 500; border: 0.5px solid; white-space: nowrap;
}
.p-up   { background: #fdf0f0; color: #7a2828; border-color: #e8b4b4; }
.p-dn   { background: #f0f7f0; color: #265226; border-color: #a8cca8; }
.p-st   { background: #f8fafc; color: #4b5563; border-color: #e2e8f0; }
.p-fc   { background: #eff6ff; color: #1a56db; border-color: #bfdbfe; }
.p-proj { background: #eff6ff; color: #1a56db; border-color: #bfdbfe; }
html.dark .p-up   { background: rgba(239,68,68,.08);  color: #fca5a5; border-color: rgba(239,68,68,.2); }
html.dark .p-dn   { background: rgba(34,197,94,.08);  color: #86efac; border-color: rgba(34,197,94,.2); }
html.dark .p-st   { background: #1a202c; color: #9ca3af; border-color: #2d3748; }
html.dark .p-fc   { background: rgba(37,99,235,.08); color: #93c5fd; border-color: rgba(37,99,235,.2); }

/* ══ COLOR UTILS ══ */
.up-txt { color: #7a2828; }
.dn-txt { color: #265226; }
.nt-txt { color: #6b7280; }
.blu-txt { color: #1a56db; }
html.dark .up-txt { color: #fca5a5; }
html.dark .dn-txt { color: #86efac; }
html.dark .nt-txt { color: #9ca3af; }
html.dark .blu-txt { color: #93c5fd; }

/* ══ FILTER ══ */
.f-sel {
    display: block; width: 100%;
    border: 0.5px solid #e2e8f0; border-radius: 6px;
    font-size: 14px; font-family: inherit; font-weight: 500;
    padding: 8px 26px 8px 10px; background: #fff; color: #0f172a;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2394a3b8' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 6px center; background-size: 14px;
    transition: border-color .15s;
}
.f-sel:focus { outline: none; border-color: #1a56db; }
html.dark .f-sel { background-color: #2d3748; border-color: #4a5568; color: #e2e8f0; }

/* ══ KPI STRIP ══ */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 14px; }
.kpi-box {
    border: 0.5px solid #e2e8f0; border-radius: 8px;
    padding: 16px 18px; background: #fff;
    border-top: 2px solid #1a56db;
    text-align: center;
}
html.dark .kpi-box { background: #1e2433; border-color: #2d3748; border-top-color: #3b82f6; }
.kpi-val { font-family: 'Inter', monospace; font-size: 24px; font-weight: 500; line-height: 1; margin-bottom: 6px; }
.kpi-lbl { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: #6b7280; }
.kpi-sub { font-size: 12px; color: #9ca3af; margin-top: 4px; text-align: center; }
html.dark .kpi-lbl { color: #9ca3af; }

/* ══ TABLE ══ */
.tbl-card { border: 0.5px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; }
html.dark .tbl-card { background: #1e2433; border-color: #2d3748; }
.topbar {
    padding: 14px 20px; display: flex; justify-content: space-between;
    align-items: flex-start; flex-wrap: wrap; gap: 8px;
    border-bottom: 0.5px solid #e2e8f0; background: #f8fafc;
}
html.dark .topbar { background: #1a202c; border-color: #2d3748; }
.legend-bar {
    display: flex; flex-wrap: wrap; gap: 14px;
    font-size: 12px; color: #6b7280;
    padding: 7px 20px; border-bottom: 0.5px solid #e2e8f0;
    background: #f8fafc;
}
html.dark .legend-bar { background: #1a202c; border-color: #2d3748; color: #9ca3af; }
.leg-line { width: 12px; height: 2px; border-radius: 1px; display: inline-block; vertical-align: middle; margin-right: 4px; }
.toolbar {
    padding: 10px 20px; display: flex; align-items: center;
    gap: 8px; flex-wrap: wrap; border-bottom: 0.5px solid #f1f5f9;
}
html.dark .toolbar { border-color: #1a202c; }
.tbl-search {
    border: 0.5px solid #e2e8f0; border-radius: 6px;
    font-size: 14px; font-family: inherit;
    padding: 7px 10px 7px 30px; background: #fff; color: #0f172a;
    width: 240px; transition: border-color .15s;
}
.tbl-search:focus { outline: none; border-color: #1a56db; }
html.dark .tbl-search { background: #2d3748; border-color: #4a5568; color: #e2e8f0; }
.sort-chip {
    font-size: 12px; padding: 5px 11px;
    border: 0.5px solid #e2e8f0; border-radius: 5px;
    background: #fff; color: #6b7280; cursor: pointer;
    transition: all .12s; font-family: inherit; font-weight: 500;
}
html.dark .sort-chip { background: #2d3748; border-color: #4a5568; color: #9ca3af; }
.sort-chip:hover { border-color: #1a56db; color: #1a56db; }
.sort-chip.active { border-color: #1a56db; background: #eff6ff; color: #1a56db; }
html.dark .sort-chip.active { background: rgba(37,99,235,.15); color: #93c5fd; border-color: #3b82f6; }

.data-tbl { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 920px; }
.data-tbl thead th {
    padding: 9px 12px; text-align: left;
    font-size: 11px; font-weight: 600; letter-spacing: .08em;
    text-transform: uppercase; color: #6b7280;
    background: #f8fafc; border-bottom: 0.5px solid #e2e8f0;
    white-space: nowrap; position: sticky; top: 0; z-index: 2;
}
html.dark .data-tbl thead th { background: #1a202c; color: #6b7280; border-color: #2d3748; }
.data-tbl thead th.r { text-align: right; }
.data-tbl thead th.c { text-align: center; }
.data-tbl tbody tr { border-bottom: none !important; transition: background .1s; }
.data-tbl tbody tr:nth-child(odd)  { background: #ffffff; }
.data-tbl tbody tr:nth-child(even) { background: #edf2f7; }
html.dark .data-tbl tbody tr:nth-child(odd)  { background: #1e2433; }
html.dark .data-tbl tbody tr:nth-child(even) { background: #161c2a; }
.data-tbl tbody tr:hover { background: #dbeafe !important; }
html.dark .data-tbl tbody tr:hover { background: rgba(59,130,246,.10) !important; }
.data-tbl td { padding: 8px 12px; color: #374151; vertical-align: middle; }
html.dark .data-tbl td { color: #d1d5db; }
.td-r { text-align: right; }
.td-c { text-align: center; }
.data-tbl tbody td:first-child { min-width: 140px; }

/* column group borders */
.g-sep { border-left: 0.5px solid #f1f5f9 !important; }
html.dark .g-sep { border-left-color: #1a202c !important; }

/* th group row */
.th-grp th {
    padding: 5px 12px; font-size: 10px; font-weight: 600;
    letter-spacing: .1em; text-transform: uppercase;
    border-bottom: none; text-align: center;
}

/* ══ MOVER ══ */
.mover-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px; transition: background .1s;
}
.mover-item:hover { background: #f8fafc; }
html.dark .mover-item:hover { background: rgba(255,255,255,.02); }
.mover-rank {
    width: 22px; height: 22px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 600; flex-shrink: 0;
    background: #f1f5f9; color: #4b5563;
}

/* ══ PAGINATION ══ */
.pg-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 30px; height: 30px; padding: 0 6px;
    font-size: 13px; font-weight: 500; border-radius: 5px;
    border: 0.5px solid #e2e8f0; background: #fff; color: #374151;
    cursor: pointer; text-decoration: none;
}
.pg-active { background: #1a56db; color: #fff; border-color: #1a56db; }

/* ══ EXPORT DROPDOWN ══ */
.exp-dd {
    position: absolute; right: 0; top: calc(100% + 4px); width: 180px;
    background: #fff; border: 0.5px solid #e2e8f0; border-radius: 7px;
    box-shadow: 0 8px 24px rgba(0,0,0,.08); z-index: 50; overflow: hidden;
}
html.dark .exp-dd { background: #1e2433; border-color: #2d3748; }
.exp-item {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px; font-size: 13px; color: #374151;
    text-decoration: none; transition: background .1s;
}
html.dark .exp-item { color: #d1d5db; }
.exp-item:hover { background: #f8fafc; }
html.dark .exp-item:hover { background: #2d3748; }

/* ══ FORECAST GRID — desktop default: 4 kolom sejajar ══ */
.fc-full-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1.5fr 1.8fr;
    gap: 12px;
    align-items: stretch;
}

/* ══ FORECAST KVBOX — CENTER ALL CONTENT ══ */
.fc-full-grid .kvbox {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-height: 130px;
    padding: 16px 14px;
}
.fc-full-grid .kvbox-l {
    text-align: center;
    width: 100%;
    margin-bottom: 10px;
}
.fc-full-grid .kvbox-v {
    text-align: center;
    width: 100%;
}
.fc-full-grid .kvbox-s {
    text-align: center;
    width: 100%;
}
.fc-full-grid .ihk-v {
    text-align: center;
    width: 100%;
    font-family: 'Inter', monospace;
    font-weight: 500;
    color: #1a56db;
}
html.dark .fc-full-grid .ihk-v { color: #93c5fd; }
.fc-full-grid .ihk-int {
    text-align: center;
    width: 100%;
    font-size: 12px;
    color: #9ca3af;
    margin-top: 4px;
}
.fc-full-grid .bar-wrap {
    width: 100%;
    margin-bottom: 0;
}
.fc-full-grid .bar-pct {
    justify-content: space-between;
    font-size: 12px;
}
.fc-full-grid .note {
    width: 100%;
    text-align: left;
    margin-top: 12px;
    margin-bottom: 0;
}

/* ══ MISC ══ */
.scrollbar-x::-webkit-scrollbar { height: 4px; }
.scrollbar-x::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
html.dark .scrollbar-x::-webkit-scrollbar-thumb { background: #4a5568; }

nav[aria-label="Pagination Navigation"] p {
    display: none !important;
}

@keyframes fadeUp { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
.fade-up { animation: fadeUp .3s ease-out; }


/* ══ MOBILE RESPONSIVE ══ */
@media (max-width: 640px) {
    .kmd { font-size: 14px; }
    .kmd > div:first-child { padding: 14px 14px 16px !important; }

    /* Header: icon + judul tetap sejajar horizontal, pills hilang */
    .kmd > div:first-child > div:first-child {
        flex-direction: row !important;
        align-items: center !important;
        gap: 10px !important;
    }

    /* Sembunyikan subtitle sumber di mobile */
    .kmd > div:first-child p {
        display: none !important;
    }

    /* Pills header juga disembunyikan (sudah ada class ini) */
    .page-header-pills { display: none !important; }
    .grid2 {
        grid-template-columns: 1fr !important;
        gap: 10px !important;
    }
    div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }

    .kpi-grid {
        grid-template-columns: 1fr 1fr !important;
        gap: 8px !important;
    }
    .kpi-box { padding: 12px 14px !important; }
    .kpi-val { font-size: 20px !important; }
    .kpi-lbl { font-size: 11px !important; }
    .kpi-sub { font-size: 11px !important; display: none; }
    .card { padding: 14px 14px !important; }
    form > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
    }

    /* Teks dalam KPI card */
    .kpi-box { text-align: center !important; }

    /* Teks dalam forecast kvbox */
    .kvbox { text-align: center !important; }
    .kvbox-l { text-align: center !important; }
    .kvbox-v { text-align: center !important; }
    .kvbox-s { text-align: center !important; }

    /* IHK row */
    .ihk-l { text-align: center !important; width: 100% !important; }
    .ihk-v { text-align: center !important; width: 100% !important; }
    .ihk-int { text-align: center !important; width: 100% !important; }

    #chartMtm, #chartYoY { height: 160px !important; }
    div[style*="height:200px"] { height: 160px !important; }
    .stat3 { gap: 6px !important; }
    .sbox { padding: 8px 4px !important; }
    .sbox-n { font-size: 18px !important; }
    .sbox-s { display: none; }
    .kv2, .kv3 { grid-template-columns: 1fr !important; }
    .ihk-row { flex-direction: column !important; align-items: flex-start !important; gap: 8px !important; }
    .page-header-pills { display: none !important; }
    .toolbar {
        padding: 8px 12px !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 8px !important;
    }
    .tbl-search { width: 100% !important; }
    .toolbar > div:last-child {
        margin-left: 0 !important;
        justify-content: flex-start !important;
    }
    .topbar {
        flex-direction: column !important;
        padding: 12px 14px !important;
        gap: 10px !important;
    }
    .topbar > div:last-child { align-self: flex-start !important; }
    .legend-bar { gap: 8px !important; padding: 6px 12px !important; }
    div[style*="justify-content: space-between"][style*="padding: 16px 28px"] {
        flex-direction: column !important;
        align-items: flex-start !important;
        padding: 12px 14px !important;
        gap: 10px !important;
    }
    div[style*="background:#1a56db; border-radius:8px; padding:10px 12px"] {
        padding: 8px 10px !important;
    }
    h1[style*="font-size:18px"] { font-size: 16px !important; }
    .note { font-size: 12px !important; }
    .sec-sub { font-size: 12px !important; margin-bottom: 10px !important; }

    /* forecast grid: 1 kolom di mobile */
    .fc-full-grid { grid-template-columns: 1fr !important; }
}

/* ══ Tablet (641–900px) ══ */
@media (min-width: 641px) and (max-width: 900px) {
    .kpi-grid {
        grid-template-columns: repeat(3, 1fr) !important;
    }
    /* forecast grid: 2 kolom di tablet */
    .fc-full-grid { grid-template-columns: 1fr 1fr !important; }
}
</style>

<!-- @php
    $nl = [1=>__('messages.bulan_januari'),2=>__('messages.bulan_februari'),3=>__('messages.bulan_maret'),4=>__('messages.bulan_april'),5=>__('messages.bulan_mei'),6=>__('messages.bulan_juni'),7=>__('messages.bulan_juli'),8=>__('messages.bulan_agustus'),9=>__('messages.bulan_september'),10=>__('messages.bulan_oktober'),11=>__('messages.bulan_november'),12=>__('messages.bulan_desember')];

    $tahunFilter         = $tahunFilter         ?? (int)date('Y');
    $bulanFilter         = $bulanFilter         ?? null;
    $analisis            = $analisis            ?? ['naik'=>0,'turun'=>0,'stabil'=>0,'inflasi'=>0,'deflasi'=>0];
    $tahunTersedia       = $tahunTersedia       ?? [(int)date('Y')];
    $daftarKomoditas     = $daftarKomoditas     ?? collect();
    $data                = $data                ?? new \Illuminate\Pagination\LengthAwarePaginator(collect(),0,20);
    $inflasiMtm          = $inflasiMtm          ?? 0;
    $inflasiYoy          = $inflasiYoy          ?? 0;
    $inflasiYtd          = $inflasiYtd          ?? 0;
    $sparkDataBulanan    = $sparkDataBulanan    ?? array_fill(0,13,0);
    $sparkDataTahunan    = $sparkDataTahunan    ?? array_fill(0,12,null);
    $sparkLabelsTahunan  = $sparkLabelsTahunan  ?? ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $yoySparkData        = $yoySparkData        ?? array_fill(0,12,null);
    $yoySparkLabels      = $yoySparkLabels      ?? ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $ihkForecast         = $ihkForecast         ?? [];
    $kondisiForecast     = $kondisiForecast     ?? null;
    $inflasiMtomForecast = $inflasiMtomForecast ?? null;
    $trend3Bulan         = $trend3Bulan         ?? null;
    $fcBulanDepan             = $ihkForecast['bulan_depan'] ?? null;
    $ihkKomoditasForecast     = $ihkKomoditasForecast     ?? [];

    $tglIni   = \Carbon\Carbon::create($tahunFilter, $bulanFilter ?? (int)date('m'), 1);
    $tglLalu  = $tglIni->copy()->subMonth();
    $tglDepan = $tglIni->copy()->addMonth();
    $lblLalu  = ($nl[$tglLalu->month]  ?? '') . ' ' . $tglLalu->year;
    $lblDepan = ($nl[$tglDepan->month] ?? '') . ' ' . $tglDepan->year;
    $lblIni   = $bulanFilter ? (($nl[$bulanFilter] ?? '') . ' ' . $tahunFilter) : 'Semua Bulan — ' . $tahunFilter;
    $lblLaluTahun = $tahunFilter - 1;
    $tahunNow = (int)date('Y');

    $sparkLabelsBulanan = [];
    for ($i = 12; $i >= 0; $i--) {
        $tgl = $tglIni->copy()->subMonths($i);
        $sparkLabelsBulanan[] = substr($nl[$tgl->month] ?? 'Bln', 0, 3) . " '" . substr($tgl->year, 2, 2);
    }

    $inflasi        = $analisis['inflasi'] ?? 0;
    $deflasi        = $analisis['deflasi'] ?? 0;
    $totalKomoditas = $data->total();
    $dominant       = ($inflasi > $deflasi) ? 'naik' : (($deflasi > $inflasi) ? 'turun' : 'stabil');
    $collection     = $data->getCollection();
    $topNaik        = $collection->where('status_mom','inflasi')->sortByDesc('persen_mom')->take(5);
    $topTurun       = $collection->where('status_mom','deflasi')->sortBy('persen_mom')->take(5);
@endphp -->
@php
$nl = [
    1  => __('messages.bulan_januari'),
    2  => __('messages.bulan_februari'),
    3  => __('messages.bulan_maret'),
    4  => __('messages.bulan_april'),
    5  => __('messages.bulan_mei'),
    6  => __('messages.bulan_juni'),
    7  => __('messages.bulan_juli'),
    8  => __('messages.bulan_agustus'),
    9  => __('messages.bulan_september'),
    10 => __('messages.bulan_oktober'),
    11 => __('messages.bulan_november'),
    12 => __('messages.bulan_desember'),];
$tahunFilter         = $tahunFilter         ?? (int)date('Y');
$bulanFilter         = $bulanFilter         ?? null;
$analisis            = $analisis            ?? ['naik'=>0,'turun'=>0,'stabil'=>0,'inflasi'=>0,'deflasi'=>0];
$tahunTersedia       = $tahunTersedia       ?? [(int)date('Y')];
$daftarKomoditas     = $daftarKomoditas     ?? collect();
$data                = $data                ?? new \Illuminate\Pagination\LengthAwarePaginator(collect(),0,20);
$inflasiMtm          = $inflasiMtm          ?? 0;
$inflasiYoy          = $inflasiYoy          ?? 0;
$inflasiYtd          = $inflasiYtd          ?? 0;
$sparkDataBulanan    = $sparkDataBulanan    ?? array_fill(0,13,null);
$sparkDataTahunan    = $sparkDataTahunan    ?? array_fill(0,12,null);
$sparkLabelsTahunan  = $sparkLabelsTahunan  ?? ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$yoySparkData        = $yoySparkData        ?? array_fill(0,12,null);
$yoySparkLabels      = $yoySparkLabels      ?? ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$ihkForecast         = $ihkForecast         ?? [];
$kondisiForecast     = $kondisiForecast     ?? null;
$inflasiMtomForecast = $inflasiMtomForecast ?? null;
$trend3Bulan         = $trend3Bulan         ?? null;
$ihkAgregatHistoris  = $ihkAgregatHistoris  ?? null;
$lastAktualTahun     = $lastAktualTahun     ?? (int)date('Y');
$lastAktualBulan     = $lastAktualBulan     ?? (int)date('m');
$fcBulanDepan        = $ihkForecast['bulan_depan'] ?? null;
$ihkKomoditasForecast = $ihkKomoditasForecast ?? [];

$tglIni   = \Carbon\Carbon::create($tahunFilter, $bulanFilter ?? (int)date('m'), 1);
$tglLalu  = $tglIni->copy()->subMonth();
$tglDepan = $tglIni->copy()->addMonth();
$lblLalu  = ($nl[$tglLalu->month]  ?? '') . ' ' . $tglLalu->year;
$lblDepan = ($nl[$tglDepan->month] ?? '') . ' ' . $tglDepan->year;
$lblIni   = $bulanFilter ? (($nl[$bulanFilter] ?? '') . ' ' . $tahunFilter) : 'Semua Bulan — ' . $tahunFilter;
$lblLaluTahun = $tahunFilter - 1;
$tahunNow = (int)date('Y');

// Deteksi apakah periode yang dipilih adalah HISTORIS atau FORECAST
// Historis = ada data aktual di price_data untuk tahun+bulan ini
// Forecast = tidak ada data aktual (bulan depan / masa depan)
$isForecastPeriod = $bulanFilter
    ? ($tahunFilter > $lastAktualTahun || ($tahunFilter === $lastAktualTahun && $bulanFilter > $lastAktualBulan))
    : false;

$sparkLabelsBulanan = [];
for ($i = 12; $i >= 0; $i--) {
    $tgl = $tglIni->copy()->subMonths($i);
    $sparkLabelsBulanan[] = substr($nl[$tgl->month] ?? 'Bln', 0, 3) . " '" . substr($tgl->year, 2, 2);
}

$inflasi        = $analisis['inflasi'] ?? 0;
$deflasi        = $analisis['deflasi'] ?? 0;
$totalKomoditas = $data->total();
$dominant       = ($inflasi > $deflasi) ? 'naik' : (($deflasi > $inflasi) ? 'turun' : 'stabil');
$collection     = $data->getCollection();
$topNaik        = $collection->where('status_mom','inflasi')->sortByDesc('persen_mom')->take(5);
$topTurun       = $collection->where('status_mom','deflasi')->sortBy('persen_mom')->take(5);
@endphp

<div class="kmd fade-up" style="padding: clamp(12px, 3vw, 22px) clamp(12px, 3vw, 22px) 60px; background: #f8fafc; min-height: 100vh;">

{{-- ══ 1. PAGE HEADER ══ --}}
<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:18px; padding-bottom:16px; border-bottom:2px solid #1a56db;">
    <div style="display:flex; align-items:center; gap:10px;">
        <div style="background:#1a56db; border-radius:8px; padding:8px 10px; color:#fff; flex-shrink:0;">
            <i class="fas fa-chart-line" style="font-size:16px;"></i>
        </div>
        <div>
            <h1 style="font-size:18px; font-weight:700; color:#0f172a; margin:0; line-height:1.2;">Monitoring Harga &amp; Proyeksi Komoditas</h1>
            <p style="font-size:12px; color:#9ca3af; margin:2px 0 0; line-height:1.4;">Sumber: <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;font-size:11px;color:#4b5563;">price_data · price_forecasts · IHK/RH</code> — diperbarui otomatis setiap minggu</p>
        </div>
    </div>
    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:5px;" class="page-header-pills">
        <span class="pill p-fc"><i class="fas fa-calendar" style="font-size:10px;"></i> {{ $lblIni }}</span>
        @if($bulanFilter)
            <span class="pill {{ $inflasiMtm >= 0 ? 'p-up' : 'p-dn' }}">MtM {{ ($inflasiMtm>=0?'+':'').number_format($inflasiMtm,2,',','.') }}%</span>
            <span class="pill {{ $inflasiYoy >= 0 ? 'p-up' : 'p-dn' }}">YoY {{ ($inflasiYoy>=0?'+':'').number_format($inflasiYoy,2,',','.') }}%</span>
            <span class="pill {{ $inflasiYtd >= 0 ? 'p-up' : 'p-dn' }}">YtD {{ ($inflasiYtd>=0?'+':'').number_format($inflasiYtd,2,',','.') }}%</span>
        @endif
    </div>
</div>

{{-- ══ 2. FILTER ══ --}}
<div class="card" style="margin-bottom:16px;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px; padding-bottom:12px; border-bottom:0.5px solid #e2e8f0;">
        <div style="background:#eff6ff; border-radius:6px; padding:7px 9px; flex-shrink:0;">
            <i class="fas fa-circle-info" style="font-size:15px; color:#1a56db;"></i>
        </div>
        <div>
            <p style="font-size:13px; font-weight:600; color:#1a56db; margin:0 0 2px;">Pilih Tahun &amp; Bulan untuk menampilkan insight</p>
            <p style="font-size:12px; color:#9ca3af; margin:0; line-height:1.5;">
                Tentukan periode terlebih dahulu, lalu klik <strong style="color:#374151;">Terapkan</strong> — data MtM, YoY, proyeksi, dan top mover akan muncul sesuai periode yang dipilih.
            </p>
        </div>
    </div>

    <form id="filterForm" action="{{ route('laporan.komoditas.index') }}" method="GET">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; align-items:end;">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;margin-bottom:4px;">Komoditas</label>
                <select name="komoditas_id" class="f-sel">
                    <option value="">Semua Komoditas</option>
                    @foreach($daftarKomoditas as $k)
                        <option value="{{ $k->id }}" {{ request('komoditas_id') == $k->id ? 'selected' : '' }}>{{ $k->nama_komoditas }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;margin-bottom:4px;">Tahun</label>
                <select name="tahun" class="f-sel">
                    @foreach($tahunTersedia as $t)
                        <option value="{{ $t }}" {{ (int)$tahunFilter===(int)$t?'selected':'' }}>{{ $t }}{{ (int)$t>$tahunNow?' (Forecast)':'' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;margin-bottom:4px;">Bulan</label>
                <select name="bulan" class="f-sel">
                    @foreach($nl as $num => $nama)
                        <option value="{{ $num }}" {{ $bulanFilter == $num ? 'selected' : '' }}>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;margin-bottom:4px;">Status Harga</label>
                <select name="status" class="f-sel">
                    <option value="">Semua Status</option>
                    <option value="naik"   {{ request('status')=='naik'   ?'selected':'' }}>Naik</option>
                    <option value="turun"  {{ request('status')=='turun'  ?'selected':'' }}>Turun</option>
                    <option value="stabil" {{ request('status')=='stabil' ?'selected':'' }}>Stabil</option>
                    <option value="proj"   {{ request('status')=='proj'   ?'selected':'' }}>Hanya Forecast</option>
                </select>
            </div>
            <div style="display:flex; gap:7px;">
                <button type="submit" style="flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:9px 12px;background:#1a56db;color:#fff;font-size:13px;font-weight:600;border:none;border-radius:6px;cursor:pointer;">
                 Terapkan
                </button>
                <a href="{{ route('laporan.komoditas.index') }}" style="display:flex;align-items:center;justify-content:center;padding:9px 12px;background:#f1f5f9;color:#6b7280;border-radius:6px;text-decoration:none;font-size:13px;" title="Reset">
                    <i class="fas fa-rotate-left" style="font-size:13px;"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ══ 3. KPI STRIP ══ --}}
<div class="kpi-grid" id="sectionInsight" style="margin-bottom:16px; scroll-margin-top:80px;">
    <div class="kpi-box">
        <div class="kpi-val" style="color:#0f172a;">{{ $totalKomoditas }}</div>
        <div class="kpi-lbl">Total Komoditas</div>
        <div class="kpi-sub">Terpantau sistem</div>
    </div>
    @if($bulanFilter)
    <div class="kpi-box" style="border-top-color:#7a2828;">
        <div class="kpi-val up-txt">{{ $inflasi }}</div>
        <div class="kpi-lbl">Harga Naik</div>
        <div class="kpi-sub">MtM {{ $lblLalu }} → {{ $lblIni }}</div>
    </div>
    <div class="kpi-box" style="border-top-color:#265226;">
        <div class="kpi-val dn-txt">{{ $deflasi }}</div>
        <div class="kpi-lbl">Harga Turun</div>
        <div class="kpi-sub">MtM {{ $lblLalu }} → {{ $lblIni }}</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val {{ $inflasiMtm>=0?'up-txt':'dn-txt' }}">{{ ($inflasiMtm>=0?'+':'').number_format($inflasiMtm,2,',','.') }}%</div>
        <div class="kpi-lbl">Rata-rata MtM</div>
        <div class="kpi-sub">Perubahan harga rata-rata</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val {{ $inflasiYoy>=0?'up-txt':'dn-txt' }}">{{ ($inflasiYoy>=0?'+':'').number_format($inflasiYoy,2,',','.') }}%</div>
        <div class="kpi-lbl">YoY vs {{ $lblLaluTahun }}</div>
        <div class="kpi-sub">Dibanding tahun lalu</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val {{ $inflasiYtd>=0?'up-txt':'dn-txt' }}">{{ ($inflasiYtd>=0?'+':'').number_format($inflasiYtd,2,',','.') }}%</div>
        <div class="kpi-lbl">YtD {{ $tahunFilter }}</div>
        <div class="kpi-sub">Akumulasi awal tahun</div>
    </div>
    @else
    <div class="kpi-box">
        <div class="kpi-val {{ $inflasiYtd>=0?'up-txt':'dn-txt' }}">{{ ($inflasiYtd>=0?'+':'').number_format($inflasiYtd,2,',','.') }}%</div>
        <div class="kpi-lbl">YtD {{ $tahunFilter }}</div>
        <div class="kpi-sub">Akumulasi awal tahun</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val {{ $inflasiYoy>=0?'up-txt':'dn-txt' }}">{{ ($inflasiYoy>=0?'+':'').number_format($inflasiYoy,2,',','.') }}%</div>
        <div class="kpi-lbl">YoY vs {{ $lblLaluTahun }}</div>
        <div class="kpi-sub">Dibanding tahun lalu</div>
    </div>
    @endif
</div>

{{-- ══ 3.5. PROYEKSI MODEL FORECAST — FULL WIDTH ══ --}}
<div class="card" style="margin-bottom:16px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:3px; flex-wrap:wrap; gap:8px;">
        <div>
            <div class="sec gray">Proyeksi Model Forecast (Prophet)</div>
            <p class="sec-sub">{{ $lblIni }} → <strong style="color:#374151;">{{ $lblDepan }}</strong></p>
        </div>
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            @if($kondisiForecast === 'inflasi')
                <span class="pill p-up" style="font-size:13px;padding:5px 13px;"><i class="fas fa-arrow-trend-up" style="font-size:10px;"></i> Prediksi Inflasi</span>
            @elseif($kondisiForecast === 'deflasi')
                <span class="pill p-dn" style="font-size:13px;padding:5px 13px;"><i class="fas fa-arrow-trend-down" style="font-size:10px;"></i> Prediksi Deflasi</span>
            @elseif($kondisiForecast === 'stabil')
                <span class="pill p-st" style="font-size:13px;padding:5px 13px;"><i class="fas fa-minus" style="font-size:10px;"></i> Prediksi Stabil</span>
            @else
                <span class="pill p-st" style="font-size:13px;padding:5px 13px;"><i class="fas fa-minus" style="font-size:10px;"></i> Belum Ada Data</span>
            @endif
            @if($trend3Bulan)
            <div style="display:flex; align-items:center; gap:6px;">
                <span style="font-size:12px;color:#9ca3af;">Tren 3 Bulan:</span>
                @if($trend3Bulan === 'meningkat')
                    <span class="pill p-up"><i class="fas fa-arrow-trend-up" style="font-size:10px;"></i> Meningkat</span>
                @elseif($trend3Bulan === 'menurun')
                    <span class="pill p-dn"><i class="fas fa-arrow-trend-down" style="font-size:10px;"></i> Menurun</span>
                @else
                    <span class="pill p-st"><i class="fas fa-minus" style="font-size:10px;"></i> Stabil</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    <div class="note info" style="margin-bottom:16px;">
        Proyeksi dari model <strong>Prophet</strong> menggunakan IHK agregat berbobot chain-linked NK BPS 2022=100. Kondisi ditentukan dari <code style="font-size:12px;background:rgba(26,79,160,.1);padding:1px 5px;border-radius:3px;">inflasi_mtom_forecast</code> agregat, threshold ±0,1%.
    </div>

    {{-- ══ Forecast Cards ══ --}}
    <div class="fc-full-grid">

        {{-- Kolom 1: Kondisi Agregat --}}
        <div class="kvbox">
            <div class="kvbox-l">Kondisi Agregat</div>
            @if($kondisiForecast === 'inflasi')
                <span class="pill p-up" style="font-size:13px;padding:5px 12px;">
                    <i class="fas fa-arrow-trend-up" style="font-size:12px;"></i> Inflasi
                </span>
            @elseif($kondisiForecast === 'deflasi')
                <span class="pill p-dn" style="font-size:13px;padding:5px 12px;">
                    <i class="fas fa-arrow-trend-down" style="font-size:12px;"></i> Deflasi
                </span>
            @elseif($kondisiForecast === 'stabil')
                <span class="pill p-st" style="font-size:13px;padding:5px 12px;">
                    <i class="fas fa-minus" style="font-size:12px;"></i> Stabil
                </span>
            @else
                <span style="font-size:13px;color:#9ca3af;">—</span>
            @endif
            <div class="kvbox-s" style="margin-top:10px;">IHK berbobot BPS</div>
        </div>

        {{-- Kolom 2: Inflasi MtM Forecast --}}
        <div class="kvbox">
            <div class="kvbox-l">Inflasi MtM Forecast</div>
            @if($inflasiMtomForecast !== null)
                <div class="kvbox-v {{ $inflasiMtomForecast > 0.1 ? 'up-txt' : ($inflasiMtomForecast < -0.1 ? 'dn-txt' : 'nt-txt') }}" style="font-size:20px;">
                    {{ ($inflasiMtomForecast >= 0 ? '+' : '') . number_format($inflasiMtomForecast, 4, ',', '.') }}%
                </div>
                <div class="kvbox-s">Threshold BPS ±0,1%</div>
            @else
                <div class="kvbox-v nt-txt">—</div>
                <div class="kvbox-s">Belum tersedia</div>
            @endif
        </div>

        {{-- Kolom 3: IHK Agregat Forecast --}}
        <div class="kvbox">
            @if($isForecastPeriod)
                {{-- FORECAST: tampilkan IHK agregat dari Flask (bulan depan) --}}
                <div class="kvbox-l">IHK Agregat Forecast — {{ $lblDepan }}</div>
                @if($fcBulanDepan)
                    <div class="ihk-v" style="font-size:26px;">
                        {{ number_format($fcBulanDepan['nilai_ihk_forecast'], 2, ',', '.') }}
                    </div>
                    <div class="ihk-int">
                        Interval 80%: {{ number_format($fcBulanDepan['ihk_lower'], 2, ',', '.') }} – {{ number_format($fcBulanDepan['ihk_upper'], 2, ',', '.') }}
                    </div>
                @else
                    <div style="font-size:13px;color:#9ca3af;">Data forecast belum tersedia</div>
                @endif
            @elseif($bulanFilter && $ihkAgregatHistoris && isset($ihkAgregatHistoris->ihk_agregat) && $ihkAgregatHistoris->ihk_agregat)
                {{-- HISTORIS: tampilkan IHK agregat aktual dari DB --}}
                <div class="kvbox-l">IHK Agregat Aktual — {{ $lblIni }}</div>
                <div class="ihk-v" style="font-size:26px;">
                    {{ number_format($ihkAgregatHistoris->ihk_agregat, 2, ',', '.') }}
                </div>
                @if(isset($ihkAgregatHistoris->mtom_aktual) && $ihkAgregatHistoris->mtom_aktual !== null)
                <div class="ihk-int">
                    MtM Aktual: {{ ($ihkAgregatHistoris->mtom_aktual >= 0 ? '+' : '') . number_format($ihkAgregatHistoris->mtom_aktual, 4, ',', '.') }}%
                </div>
                @endif
            @else
                {{-- Tidak ada data --}}
                <div class="kvbox-l">IHK Agregat</div>
                <div style="font-size:13px;color:#9ca3af; margin-top: 8px;">
                    @if($bulanFilter)
                        Data IHK untuk periode ini belum tersedia.<br>
                        <span style="font-size:11px;">Pastikan tabel <code>andil_inflasi_bulanan</code> terisi.</span>
                    @else
                        Pilih bulan tertentu untuk melihat IHK agregat.
                    @endif
                </div>
            @endif
        </div>

        {{-- Kolom 4: Distribusi 3 Bulan --}}
        <div class="kvbox">
            @if(!empty($ihkForecast['forecast_3_bulan']))
            @php
                $fc3        = $ihkForecast['forecast_3_bulan'];
                $fc3Inflasi = collect($fc3)->where('kondisi_forecast','inflasi')->count();
                $fc3Deflasi = collect($fc3)->where('kondisi_forecast','deflasi')->count();
                $fc3Stabil  = collect($fc3)->where('kondisi_forecast','stabil')->count();
                $fc3Total   = count($fc3);
                $pFc3I = $fc3Total > 0 ? round($fc3Inflasi / $fc3Total * 100) : 0;
                $pFc3D = $fc3Total > 0 ? round($fc3Deflasi / $fc3Total * 100) : 0;
                $pFc3S = $fc3Total > 0 ? round($fc3Stabil  / $fc3Total * 100) : 0;
            @endphp
            <div class="kvbox-l">Distribusi Kondisi 3 Bulan ke Depan</div>
            <div class="bar-wrap" style="margin-top:8px;">
                <div class="bar-track" style="height:6px;">
                    @if($pFc3I > 0)<div style="width:{{ $pFc3I }}%;background:#b84848;"></div>@endif
                    @if($pFc3S > 0)<div style="width:{{ $pFc3S }}%;background:#d1d5db;"></div>@endif
                    @if($pFc3D > 0)<div style="width:{{ $pFc3D }}%;background:#3b6d11;"></div>@endif
                </div>
                <div class="bar-pct" style="font-size:13px; margin-top:10px;">
                    <span class="up-txt" style="font-weight:500;">{{ $pFc3I }}% inflasi</span>
                    <span class="nt-txt">{{ $pFc3S }}% stabil</span>
                    <span class="dn-txt" style="font-weight:500;">{{ $pFc3D }}% deflasi</span>
                </div>
            </div>
            @endif
            <div class="note" style="font-size:11px;line-height:1.6;margin-top:12px;margin-bottom:0;text-align:left;">
                MtM &gt; +0,1% = Inflasi &nbsp;·&nbsp; MtM &lt; −0,1% = Deflasi &nbsp;·&nbsp; lainnya = Stabil.<br>
                Kondisi dari IHK agregat berbobot, bukan jumlah komoditas naik/turun.
            </div>
        </div>

    </div>{{-- end fc-full-grid --}}
</div>

{{-- ══ 4. CHARTS ══ --}}
<div class="grid2" style="margin-bottom:14px;">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2px;">
            <div class="sec">@if($bulanFilter) Tren Perubahan Harga (13 Bulan) @else Perubahan Harga Per Bulan — {{ $tahunFilter }} @endif</div>
            <span style="font-size:12px;color:#9ca3af;background:#f8fafc;padding:2px 8px;border-radius:4px;border:0.5px solid #e2e8f0;font-family:'Inter',monospace;">MtM %</span>
        </div>
        <p class="sec-sub">Rata-rata perubahan harga (%) seluruh komoditas vs bulan sebelumnya</p>
        <div style="position:relative;height:200px;">
            <canvas id="chartMtm"></canvas>
        </div>
    </div>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2px;">
            <div class="sec gray">Perubahan YoY Per Bulan — {{ $tahunFilter }}</div>
            <span style="font-size:12px;color:#9ca3af;background:#f8fafc;padding:2px 8px;border-radius:4px;border:0.5px solid #e2e8f0;font-family:'Inter',monospace;">YoY %</span>
        </div>
        <p class="sec-sub">Perbandingan rata-rata harga tiap bulan vs bulan yang sama tahun {{ $tahunFilter - 1 }}</p>
        <div style="position:relative;height:200px;">
            <canvas id="chartYoY"></canvas>
        </div>
    </div>
</div>

{{-- ══ 6. TOP MOVERS ══ --}}
@if($bulanFilter && ($topNaik->count() > 0 || $topTurun->count() > 0))
<div class="grid2" style="margin-bottom:14px;">
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:12px 16px;border-bottom:0.5px solid #f1f5f9;display:flex;align-items:center;gap:9px;">
            <i class="fas fa-arrow-trend-up up-txt" style="font-size:15px;"></i>
            <div>
                <p style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#7a2828;margin:0;">Top 5 Kenaikan MtM</p>
                <p style="font-size:12px;color:#9ca3af;margin:2px 0 0;">{{ $lblLalu }} → {{ $lblIni }}</p>
            </div>
        </div>
        @forelse($topNaik as $item)
        <div class="mover-item" style="{{ !$loop->last ? 'border-bottom:0.5px solid #f8fafc;' : '' }}">
            <div style="display:flex;align-items:center;gap:9px;">
                <div class="mover-rank">{{ $loop->iteration }}</div>
                <div>
                    <p style="font-size:14px;font-weight:500;color:#0f172a;margin:0;">{{ $item->nama_komoditas }}</p>
                    @if($item->harga_bulan_lalu && $item->harga_bulan_ini)
                    <p style="font-size:12px;color:#9ca3af;margin:2px 0 0;">
                        Rp {{ number_format($item->harga_bulan_lalu,0,',','.') }}
                        → Rp {{ number_format($item->harga_bulan_ini,0,',','.') }}
                    </p>
                    @endif
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div class="mono up-txt" style="font-size:14px;font-weight:500;">+{{ number_format($item->persen_mom,2,',','.') }}%</div>
                @if($item->selisih_mom > 0)
                <div style="font-size:12px;color:#9ca3af;">+Rp {{ number_format($item->selisih_mom,0,',','.') }}</div>
                @endif
            </div>
        </div>
        @empty
        <div style="padding:20px;text-align:center;font-size:14px;color:#9ca3af;">Tidak ada data kenaikan</div>
        @endforelse
    </div>
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:12px 16px;border-bottom:0.5px solid #f1f5f9;display:flex;align-items:center;gap:9px;">
            <i class="fas fa-arrow-trend-down dn-txt" style="font-size:15px;"></i>
            <div>
                <p style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#265226;margin:0;">Top 5 Penurunan MtM</p>
                <p style="font-size:12px;color:#9ca3af;margin:2px 0 0;">{{ $lblLalu }} → {{ $lblIni }}</p>
            </div>
        </div>
        @forelse($topTurun as $item)
        <div class="mover-item" style="{{ !$loop->last ? 'border-bottom:0.5px solid #f8fafc;' : '' }}">
            <div style="display:flex;align-items:center;gap:9px;">
                <div class="mover-rank">{{ $loop->iteration }}</div>
                <div>
                    <p style="font-size:14px;font-weight:500;color:#0f172a;margin:0;">{{ $item->nama_komoditas }}</p>
                    @if($item->harga_bulan_lalu && $item->harga_bulan_ini)
                    <p style="font-size:12px;color:#9ca3af;margin:2px 0 0;">
                        Rp {{ number_format($item->harga_bulan_lalu,0,',','.') }}
                        → Rp {{ number_format($item->harga_bulan_ini,0,',','.') }}
                    </p>
                    @endif
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div class="mono dn-txt" style="font-size:14px;font-weight:500;">{{ number_format($item->persen_mom,2,',','.') }}%</div>
                @if($item->selisih_mom < 0)
                <div style="font-size:12px;color:#9ca3af;">−Rp {{ number_format(abs($item->selisih_mom),0,',','.') }}</div>
                @endif
            </div>
        </div>
        @empty
        <div style="padding:20px;text-align:center;font-size:14px;color:#9ca3af;">Tidak ada data penurunan</div>
        @endforelse
    </div>
</div>
@endif

{{-- ══ 7. TABEL DETAIL ══ --}}
<div class="tbl-card" id="tabelDetail" x-data="{ exportOpen: false }" style="scroll-margin-top:80px;">
    <div class="topbar">
        <div>
            <div class="sec">Detail Perbandingan Harga Komoditas</div>
            <p style="font-size:13px;color:#6b7280;margin:4px 0 0 9px;">
                @if($bulanFilter)
                    Aktual: {{ $lblLalu }} → {{ $lblIni }}
                    <span style="margin:0 5px;color:#d1d5db;">·</span>
                    Proyeksi: <span class="blu-txt" style="font-weight:500;">{{ $lblDepan }}</span>
                    <span style="margin:0 5px;color:#d1d5db;">·</span>
                    YoY vs {{ $lblLaluTahun }}
                    <span style="margin:0 5px;color:#d1d5db;">·</span>
                    YtD {{ $tahunFilter }}
                @else
                    Rata-rata harga seluruh bulan — {{ $tahunFilter }}
                @endif
            </p>
        </div>
        <div style="position:relative;flex-shrink:0;">
            <button @click="exportOpen = !exportOpen" @click.outside="exportOpen = false"
                    style="display:flex;align-items:center;gap:5px;padding:8px 14px;background:#1a56db;color:#fff;font-size:13px;font-weight:600;border:none;border-radius:6px;cursor:pointer;">
                <i class="fas fa-download" style="font-size:11px;"></i> Ekspor
                <i class="fas fa-chevron-down" style="font-size:10px;" :style="exportOpen?'transform:rotate(180deg)':''"></i>
            </button>
            <div x-show="exportOpen" x-transition class="exp-dd" style="display:none;">
                <a href="{{ route('laporan.komoditas.cetak', request()->all()) }}" target="_blank" class="exp-item">
                    <i class="fas fa-print" style="color:#6b7280;width:14px;"></i> Cetak Laporan
                </a>
                <hr style="border:none;border-top:0.5px solid #f1f5f9;margin:2px 0;">
                <a href="{{ route('laporan.komoditas.pdf', request()->all()) }}" class="exp-item">
                    <i class="fas fa-file-pdf" style="color:#7a2828;width:14px;"></i> Unduh PDF
                </a>
                <a href="{{ route('laporan.komoditas.csv', request()->all()) }}" class="exp-item">
                    <i class="fas fa-file-csv" style="color:#265226;width:14px;"></i> Unduh CSV
                </a>
            </div>
        </div>
    </div>

    <div class="legend-bar">
        <span><span class="leg-line" style="background:#d97706;"></span>YtD</span>
        <span><span class="leg-line" style="background:#374151;"></span>YoY</span>
        <span><span class="leg-line" style="background:#4b5563;"></span>IHK / RH</span>
        <span><span class="leg-line" style="background:#1a56db;"></span>Forecast Prophet ({{ $bulanFilter ? $lblDepan : 'Proyeksi' }})</span>
        @if($bulanFilter)
        <span><span class="leg-line" style="background:#9ca3af;border-top:1px dashed #9ca3af;"></span>
            <span style="color:#9ca3af;font-style:italic;">est. = estimasi dari forecast bulan ini</span>
        </span>
        @endif
    </div>

    <div class="toolbar">
        <div style="position:relative;width:240px;">
            <i class="fas fa-magnifying-glass" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:11px;pointer-events:none;"></i>
            <input type="text" id="tblSearch" class="tbl-search" placeholder="Cari komoditas..." oninput="filterTable(this.value)">
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-left:auto;">
            <button class="sort-chip active" onclick="sortTable(event,'nama')">A–Z</button>
            <button class="sort-chip" onclick="sortTable(event,'mom_desc')">MtM ↓</button>
            <button class="sort-chip" onclick="sortTable(event,'mom_asc')">MtM ↑</button>
            <button class="sort-chip" onclick="sortTable(event,'yoy_desc')">YoY ↓</button>
            <button class="sort-chip" onclick="sortTable(event,'harga_desc')">Harga ↓</button>
        </div>
    </div>

    <div class="scrollbar-x" style="overflow-x:auto;">
        <table class="data-tbl" id="mainTable">
            <thead>
                <tr class="th-grp">
                    <th style="background:#f8fafc;text-align:left;"></th>
                    <th colspan="2" style="text-align:center;background:#eff6ff;color:#1a56db;">Harga Aktual</th>
                    @if($bulanFilter)
                    <th colspan="3" style="text-align:center;background:#f8fafc;color:#4b5563;border-left:0.5px solid #e2e8f0;">Perubahan MtM</th>
                    <th colspan="2" style="text-align:center;background:#f8fafc;color:#4b5563;border-left:0.5px solid #e2e8f0;">YtD {{ $tahunFilter }}</th>
                    <th colspan="2" style="text-align:center;background:#f8fafc;color:#4b5563;border-left:0.5px solid #e2e8f0;">YoY vs {{ $lblLaluTahun }}</th>
                    @endif
                    <th colspan="2" style="text-align:center;background:#f8fafc;color:#4b5563;border-left:0.5px solid #e2e8f0;">Indeks IHK / RH</th>
                    <th colspan="3" style="text-align:center;background:#eff6ff;color:#1a56db;border-left:0.5px solid #e2e8f0;">Forecast — {{ $lblDepan }}</th>
                </tr>
                <tr>
                    <th>Komoditas</th>
                    <th class="r">Harga {{ $bulanFilter ? $lblIni : $tahunFilter }}</th>
                    <th class="r">Harga {{ $bulanFilter ? $lblLalu : ($tahunFilter-1) }}</th>
                    @if($bulanFilter)
                    <th class="r g-sep">Selisih</th>
                    <th class="r">% MtM</th>
                    <th class="c">Status</th>
                    <th class="r g-sep">% YtD</th>
                    <th class="c">Status</th>
                    <th class="r g-sep">% YoY</th>
                    <th class="c">Status</th>
                    @endif
                    <th class="r g-sep">RH</th>
                    <th class="r">IHK</th>
                    <th class="r g-sep">Prediksi Harga</th>
                    <th class="r">IHK Forecast</th>
                    <th class="c">Tren</th>
                </tr>
            </thead>
            <tbody id="mainTbody">
            @forelse($data as $item)
                @php
                    $isAktual   = $item->harga_bulan_ini !== null;
                    $isForecast = !$isAktual;
                    $hargaTampil = $item->harga_bulan_ini ?? $item->harga_bulan_ini_est ?? null;
                @endphp
                <tr class="row-item"
                    data-nama="{{ strtolower($item->nama_komoditas) }}"
                    data-mom="{{ $item->persen_mom ?? 0 }}"
                    data-yoy="{{ $item->persen_yoy ?? 0 }}"
                    data-ytd="{{ $item->persen_ytd ?? 0 }}"
                    data-harga="{{ $hargaTampil ?? 0 }}">

                    <td>
                        <span style="font-size:14px;font-weight:500;color:#0f172a;">{{ $item->nama_komoditas }}</span>
                        @if($isForecast)
                            <span class="pill p-fc" style="margin-left:5px;font-size:11px;padding:2px 7px;">Forecast</span>
                        @endif
                    </td>

                    <td class="td-r">
                        @if($isAktual)
                            <span class="mono" style="font-size:14px;font-weight:500;">
                                Rp {{ number_format($item->harga_bulan_ini, 0, ',', '.') }}
                            </span>
                        @elseif(isset($item->harga_bulan_ini_est) && $item->harga_bulan_ini_est !== null)
                            <span class="mono blu-txt" style="font-size:14px;font-weight:500;"
                                  title="Estimasi dari model forecast bulan {{ $lblIni }}">
                                Rp {{ number_format($item->harga_bulan_ini_est, 0, ',', '.') }}
                            </span>
                            <span style="display:block;font-size:11px;color:#9ca3af;margin-top:1px;">est.</span>
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-r">
                        @if($item->harga_bulan_lalu)
                            <span class="mono" style="font-size:14px;color:#6b7280;">Rp {{ number_format($item->harga_bulan_lalu,0,',','.') }}</span>
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    @if($bulanFilter)
                    <td class="td-r g-sep">
                        @if($isAktual && $item->selisih_mom !== null)
                            @if($item->selisih_mom > 0)
                                <span class="mono up-txt" style="font-size:14px;">+Rp {{ number_format($item->selisih_mom,0,',','.') }}</span>
                            @elseif($item->selisih_mom < 0)
                                <span class="mono dn-txt" style="font-size:14px;">−Rp {{ number_format(abs($item->selisih_mom),0,',','.') }}</span>
                            @else
                                <span class="mono nt-txt" style="font-size:14px;">Rp 0</span>
                            @endif
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-r">
                        @if($isAktual && $item->persen_mom !== null)
                            @if($item->persen_mom > 0)
                                <span class="mono up-txt" style="font-size:14px;font-weight:500;">+{{ number_format($item->persen_mom,2,',','.') }}%</span>
                            @elseif($item->persen_mom < 0)
                                <span class="mono dn-txt" style="font-size:14px;font-weight:500;">{{ number_format($item->persen_mom,2,',','.') }}%</span>
                            @else
                                <span class="mono nt-txt" style="font-size:14px;">0,00%</span>
                            @endif
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-c">
                        @if($isAktual)
                            @switch($item->status_mom)
                                @case('inflasi')  <span class="pill p-up">Naik</span> @break
                                @case('deflasi')  <span class="pill p-dn">Turun</span> @break
                                @case('stabil')   <span class="pill p-st">Stabil</span> @break
                                @default          <span style="color:#d1d5db;">—</span>
                            @endswitch
                        @else
                            <span class="pill p-fc">Proyeksi</span>
                        @endif
                    </td>

                    <td class="td-r g-sep">
                        @if($isAktual && isset($item->persen_ytd) && $item->persen_ytd !== null)
                            <span class="mono {{ $item->persen_ytd > 0 ? 'up-txt' : ($item->persen_ytd < 0 ? 'dn-txt' : 'nt-txt') }}" style="font-size:14px;">
                                {{ ($item->persen_ytd > 0 ? '+' : '') . number_format($item->persen_ytd,2,',','.') }}%
                            </span>
                            @if(isset($item->harga_awal_tahun) && $item->harga_awal_tahun)
                            <span style="display:block;font-size:12px;color:#9ca3af;margin-top:1px;">Jan: Rp {{ number_format($item->harga_awal_tahun,0,',','.') }}</span>
                            @endif
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-c">
                        @if($isAktual && isset($item->persen_ytd) && $item->persen_ytd !== null)
                            @if($item->persen_ytd > 0.5)     <span class="pill p-up">Naik</span>
                            @elseif($item->persen_ytd < -0.5) <span class="pill p-dn">Turun</span>
                            @else                              <span class="pill p-st">Stabil</span>
                            @endif
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-r g-sep">
                        @if($isAktual && isset($item->persen_yoy) && $item->persen_yoy !== null)
                            <span class="mono {{ $item->persen_yoy > 0 ? 'up-txt' : ($item->persen_yoy < 0 ? 'dn-txt' : 'nt-txt') }}" style="font-size:14px;">
                                {{ ($item->persen_yoy > 0 ? '+' : '') . number_format($item->persen_yoy,2,',','.') }}%
                            </span>
                            @if(isset($item->harga_tahun_lalu) && $item->harga_tahun_lalu)
                            <span style="display:block;font-size:12px;color:#9ca3af;margin-top:1px;">{{ $lblLaluTahun }}: Rp {{ number_format($item->harga_tahun_lalu,0,',','.') }}</span>
                            @endif
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-c">
                        @if($isAktual && isset($item->persen_yoy) && $item->persen_yoy !== null)
                            @if($item->persen_yoy > 0.5)     <span class="pill p-up">Naik</span>
                            @elseif($item->persen_yoy < -0.5) <span class="pill p-dn">Turun</span>
                            @else                              <span class="pill p-st">Stabil</span>
                            @endif
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>
                    @endif

                    <td class="td-r g-sep">
                        @if($isAktual)
                            @if(isset($item->rh) && $item->rh !== null)
                                <span class="mono nt-txt" style="font-size:14px;">{{ number_format($item->rh,2,',','.') }}</span>
                            @elseif($item->harga_bulan_ini && $item->harga_bulan_lalu && $item->harga_bulan_lalu > 0)
                                @php $rh = $item->harga_bulan_ini / $item->harga_bulan_lalu * 100; @endphp
                                <span class="mono nt-txt" style="font-size:14px;">{{ number_format($rh,2,',','.') }}</span>
                            @else
                                <span style="color:#d1d5db;">—</span>
                            @endif
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-r">
                        @if($isAktual && isset($item->ihk) && $item->ihk !== null)
                            <span class="mono nt-txt" style="font-size:14px;font-weight:500;">{{ number_format($item->ihk,2,',','.') }}</span>
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-r g-sep">
                        @if(isset($item->harga_prediksi) && $item->harga_prediksi !== null)
                            <span class="mono blu-txt" style="font-size:14px;font-weight:500;">
                                Rp {{ number_format($item->harga_prediksi, 0, ',', '.') }}
                            </span>
                            @php
                                $baseHarga = $item->harga_bulan_ini
                                          ?? $item->harga_bulan_ini_est
                                          ?? $item->harga_bulan_lalu;
                                $selPred   = $baseHarga ? ($item->harga_prediksi - $baseHarga) : null;
                            @endphp
                            @if($selPred !== null)
                                <span style="display:block;font-size:12px;margin-top:1px;
                                    color:{{ $selPred > 0 ? '#7a2828' : ($selPred < 0 ? '#265226' : '#9ca3af') }};">
                                    {{ $selPred > 0 ? '+' : '' }}Rp {{ number_format($selPred, 0, ',', '.') }}
                                </span>
                            @endif
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-r">
                        @php $fcKmd = $ihkKomoditasForecast[$item->komoditas_id] ?? null; @endphp
                        @if($fcKmd)
                            <span class="mono nt-txt" style="font-size:14px;font-weight:500;">
                                {{ number_format($fcKmd['nilai_ihk_forecast'], 2, ',', '.') }}
                            </span>
                            <span style="display:block;font-size:12px;color:#9ca3af;margin-top:1px;">
                                {{ number_format($fcKmd['ihk_lower'], 2, ',', '.') }}–{{ number_format($fcKmd['ihk_upper'], 2, ',', '.') }}
                            </span>
                            @php $kd = $fcKmd['kondisi_forecast'] ?? null; @endphp
                            <span style="display:block;font-size:12px;font-weight:500;margin-top:1px;
                                color:{{ $kd==='inflasi'?'#7a2828':($kd==='deflasi'?'#265226':'#6b7280') }};">
                                {{ ucfirst($kd ?? '—') }}
                            </span>
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                    <td class="td-c">
                        @if(isset($item->tren_model) && $item->tren_model !== null)
                            @if($item->tren_model === 'naik')
                                <span style="display:inline-flex;align-items:center;gap:4px;color:#265226;font-size:13px;font-weight:500;">
                                    <i class="fas fa-arrow-up" style="font-size:11px;"></i> Naik
                                </span>
                            @elseif($item->tren_model === 'turun')
                                <span style="display:inline-flex;align-items:center;gap:4px;color:#7a2828;font-size:13px;font-weight:500;">
                                    <i class="fas fa-arrow-down" style="font-size:11px;"></i> Turun
                                </span>
                            @else
                                <span style="display:inline-flex;align-items:center;gap:4px;color:#6b7280;font-size:13px;font-weight:500;">
                                    <i class="fas fa-minus" style="font-size:11px;"></i> Stabil
                                </span>
                            @endif
                        @else
                            <span style="color:#d1d5db;">—</span>
                        @endif
                    </td>

                </tr>
            @empty
                <tr>
                    <td colspan="{{ $bulanFilter ? 15 : 8 }}">
                        <div style="display:flex;flex-direction:column;align-items:center;padding:48px 0;color:#d1d5db;gap:8px;">
                            <i class="fas fa-box-open" style="font-size:28px;"></i>
                            <p style="font-size:14px;color:#9ca3af;">{{ __('messages.data_tidak_ditemukan') }}</p>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($data->hasPages())
    <div style="padding:16px 28px; display:flex; align-items:center; justify-content:space-between; border-top:0.5px solid #f1f5f9; background:#f8fafc; gap:20px; flex-wrap:wrap;">
        <span style="font-size:13px; color:#6b7280; white-space:nowrap; flex-shrink:0;">
            Menampilkan {{ $data->firstItem() }}–{{ $data->lastItem() }} dari {{ $data->total() }} data
        </span>
        <div style="flex-shrink:0;">
            {{ $data->appends(request()->all())->links() }}
        </div>
    </div>
    @endif
</div>

</div>{{-- end kmd --}}

{{-- ══ SCRIPTS ══ --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ─────────────────────────────────────────────
   UTILITAS SCROLL
───────────────────────────────────────────── */
function scrollToEl(el) {
    if (!el) return;
    const navH = document.querySelector('nav')?.offsetHeight ?? 64;
    const top  = el.getBoundingClientRect().top + window.scrollY - navH - 16;
    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
}

/* ─────────────────────────────────────────────
   CHARTS
───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    const dark    = document.documentElement.classList.contains('dark');
    const gridClr = dark ? 'rgba(255,255,255,.04)' : 'rgba(0,0,0,.04)';
    const lblClr  = dark ? '#4b5563' : '#9ca3af';
    const tFont   = { size: 11, family: "'Inter', sans-serif" };
    const mFont   = { size: 11, family: "'Inter', monospace" };

    const tip = {
        backgroundColor: dark ? '#1e2433' : '#fff',
        titleColor:      dark ? '#f1f5f9' : '#0f172a',
        bodyColor:       dark ? '#94a3b8' : '#4b5563',
        borderColor:     dark ? '#374151' : '#e2e8f0',
        borderWidth: 1, padding: 10, cornerRadius: 6,
        titleFont: { size: 12, weight: '500', family: "'Inter',sans-serif" },
        bodyFont:  { size: 12, family: "'Inter',monospace" },
    };

    const scaleY = {
        grid: { color: gridClr },
        border: { dash: [3, 3] },
        ticks: { color: lblClr, font: mFont, callback: v => (v > 0 ? '+' : '') + v.toFixed(2).replace('.', ',') + '%' }
    };
    const scaleX = { grid: { color: gridClr }, ticks: { color: lblClr, font: tFont, maxRotation: 35 } };

    /* ── MtM Line Chart ── */
    const ctxMtm = document.getElementById('chartMtm');
    if (ctxMtm) {
        @if($bulanFilter)
        const labsMtm = @json($sparkLabelsBulanan);
        const dataMtm = @json($sparkDataBulanan);
        @else
        const labsMtm = @json($sparkLabelsTahunan);
        const dataMtm = @json($sparkDataTahunan);
        @endif

        const g2d  = ctxMtm.getContext('2d');
        const grad = g2d.createLinearGradient(0, 0, 0, 200);
        grad.addColorStop(0, 'rgba(26,79,160,0.08)');
        grad.addColorStop(1, 'rgba(26,79,160,0.0)');

        new Chart(ctxMtm, {
            type: 'line',
            data: { labels: labsMtm, datasets: [{
                label: '% MtM', data: dataMtm,
                borderColor: '#1a56db', backgroundColor: grad,
                borderWidth: 1.5,
                pointRadius: 3, pointBackgroundColor: '#1a56db',
                pointBorderColor: '#fff', pointBorderWidth: 1.5,
                pointHoverRadius: 5,
                tension: 0.4, fill: true,
                spanGaps: true,  // ← skip titik null, tidak digambar sebagai 0
            }]},
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: { ...tip, callbacks: {
                        label: ctx => {
                            const v = ctx.parsed.y;
                            if (v === null || v === undefined) return '  Belum ada data';
                            return `  MtM: ${v > 0 ? '+' : ''}${v.toFixed(2).replace('.', ',')}%`;
                        }
                    }}
                },
                scales: { x: scaleX, y: scaleY }
            }
        });
    } // ← penutup if (ctxMtm) yang benar

    /* ── YoY Bar Chart ── */
    const ctxYoy = document.getElementById('chartYoY');
if (ctxYoy) {
    const labsYoy = @json($yoySparkLabels);
    const dataYoy = @json($yoySparkData); // tetap 12 bulan, null untuk yang belum ada

    const barBg  = dataYoy.map(v => v === null ? 'rgba(209,213,219,.2)' : v > 0 ? 'rgba(26,79,160,.65)' : 'rgba(59,109,17,.65)');
    const barBrd = dataYoy.map(v => v === null ? 'rgba(209,213,219,.3)' : v > 0 ? '#1a56db' : '#3b6d11');

    new Chart(ctxYoy, {
        type: 'bar',
        data: { labels: labsYoy, datasets: [{
            label: '% YoY', data: dataYoy,
            backgroundColor: barBg, borderColor: barBrd,
            borderWidth: 0.5, borderRadius: 3, borderSkipped: false,
            maxBarThickness: 40,
        }]},
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { ...tip, callbacks: {
                    label: ctx => {
                        const v = ctx.parsed.y;
                        if (v === null) return '  Belum ada data';
                        return `  YoY: ${v >= 0 ? '+' : ''}${v.toFixed(2).replace('.', ',')}%`;
                    }
                }}
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: lblClr, font: tFont },
                    categoryPercentage: 0.5,
                    barPercentage: 0.6,
                },
                y: scaleY
            }
        }
    });
} // ← penutup if (ctxYoy)

}); // ← penutup DOMContentLoaded

/* ─────────────────────────────────────────────
   SCROLL OTOMATIS SETELAH LOAD
───────────────────────────────────────────── */
window.addEventListener('load', function () {
    const params = new URLSearchParams(window.location.search);
    const hash   = window.location.hash;

    if (hash === '#sectionInsight') {
        setTimeout(() => scrollToEl(document.getElementById('sectionInsight')), 100);
    } else if (hash === '#tabelDetail' || params.has('page')) {
        setTimeout(() => scrollToEl(document.getElementById('tabelDetail')), 100);
    } else if (params.get('scroll') === 'insight') {
        setTimeout(() => scrollToEl(document.getElementById('sectionInsight')), 100);
    }
});

/* ─────────────────────────────────────────────
   INTERCEPT FORM FILTER
───────────────────────────────────────────── */
document.getElementById('filterForm').addEventListener('submit', function (e) {
    if (!this.querySelector('input[name="scroll"]')) {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'scroll';
        inp.value = 'insight';
        this.appendChild(inp);
    }
});

/* ─────────────────────────────────────────────
   INTERCEPT KLIK PAGINATION
───────────────────────────────────────────── */
document.addEventListener('click', function (e) {
    const link = e.target.closest('a[href]');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href || href === '#' || href.startsWith('javascript')) return;

    const isInsidePagination = link.closest(
        'nav[aria-label="Pagination Navigation"], ' +
        '[data-pagination], ' +
        '.pagination'
    );
    if (!isInsidePagination) return;

    if (href.includes('#')) return;

    e.preventDefault();
    window.location.href = href + '#tabelDetail';
});

/* ─────────────────────────────────────────────
   FILTER TABEL (client-side search)
───────────────────────────────────────────── */
function filterTable(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#mainTbody .row-item').forEach(function (tr) {
        tr.style.display = (!q || tr.dataset.nama.includes(q)) ? '' : 'none';
    });
}

/* ─────────────────────────────────────────────
   SORT TABEL (client-side sort)
───────────────────────────────────────────── */
function sortTable(e, key) {
    document.querySelectorAll('.sort-chip').forEach(function (b) { b.classList.remove('active'); });
    e.target.classList.add('active');
    const tbody = document.getElementById('mainTbody');
    const rows  = Array.from(tbody.querySelectorAll('.row-item'));
    rows.sort(function (a, b) {
        switch (key) {
            case 'nama':       return a.dataset.nama.localeCompare(b.dataset.nama);
            case 'mom_desc':   return parseFloat(b.dataset.mom)   - parseFloat(a.dataset.mom);
            case 'mom_asc':    return parseFloat(a.dataset.mom)   - parseFloat(b.dataset.mom);
            case 'yoy_desc':   return parseFloat(b.dataset.yoy)   - parseFloat(a.dataset.yoy);
            case 'ytd_desc':   return parseFloat(b.dataset.ytd)   - parseFloat(a.dataset.ytd);
            case 'harga_desc': return parseFloat(b.dataset.harga) - parseFloat(a.dataset.harga);
            default: return 0;
        }
    });
    rows.forEach(function (r) { tbody.appendChild(r); });
}
</script>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection