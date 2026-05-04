<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\MasterKomoditas;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanKomoditasController extends Controller
{
    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $tahunAktual   = DB::table('price_data')
            ->selectRaw('YEAR(tanggal) as tahun')->distinct()->pluck('tahun');
        $tahunForecast = DB::table('price_forecasts')
            ->selectRaw('YEAR(tanggal) as tahun')->distinct()->pluck('tahun');
        $tahunTersedia = $tahunAktual->merge($tahunForecast)
            ->unique()->sortDesc()->values();
        $tahunSekarang = (int) date('Y');

        $tahunFilter  = (int) ($request->tahun ?? $tahunTersedia->first() ?? $tahunSekarang);
        $komoditasId  = $request->filled('komoditas_id') ? (int) $request->komoditas_id : null;
        $varianFilter = $request->filled('varian')       ? $request->varian             : null;

        // FIX 1: Default bulan ke bulan terakhir yang ada data aktual
        if ($request->filled('bulan')) {
            $bulanFilter = (int) $request->bulan;
        } else {
            if ($request->isMethod('GET') && !$request->has('bulan')) {
                $bulanTerakhir = DB::table('price_data')
                    ->whereYear('tanggal', $tahunFilter)
                    ->where('harga', '>', 0)
                    ->selectRaw('MONTH(MAX(tanggal)) as bulan')
                    ->value('bulan');
                $bulanFilter = $bulanTerakhir ? (int) $bulanTerakhir : null;
            } else {
                $bulanFilter = null;
            }
        }

        $daftarKomoditas = MasterKomoditas::orderBy('nama_komoditas')->get();

        [$rows, $analisis] = $this->buildRows(
            $tahunFilter, $bulanFilter, $komoditasId, $varianFilter
        );

        $perPage     = 20;
        $currentPage = max(1, (int) ($request->page ?? 1));
        $data        = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($currentPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $ihkData     = $this->getIhkData();
        $ihkForecast = [];
        try {
            $fcResponse = Http::timeout(5)->get('http://127.0.0.1:5000/api/ihk/forecast/summary');
            if ($fcResponse->successful()) {
                $ihkForecast = $fcResponse->json('data') ?? [];
            }
        } catch (\Exception $e) {
            \Log::warning('Flask IHK Forecast tidak tersedia: ' . $e->getMessage());
        }
        $inflasiYtd = (float) ($ihkData['inflasi_ytd'] ?? 0.0);

        $sparkDataBulanan   = [];
        $sparkDataTahunan   = [];
        $sparkLabelsTahunan = [];
        $inflasiMtm         = 0.0;
        $inflasiYoy         = 0.0;
        $yoySparkData       = array_fill(0, 12, null);
        $yoySparkLabels     = [];

        $bMap = $this->namaBulanMap();
        foreach ($bMap as $b) {
            $yoySparkLabels[] = substr($b, 0, 3);
        }

        if ($bulanFilter) {
            $tglIni = Carbon::create($tahunFilter, $bulanFilter, 1);

            for ($i = 12; $i >= 0; $i--) {
                $titik     = $tglIni->copy()->subMonths($i);
                $titikLalu = $titik->copy()->subMonth();

                $hIniAvg  = DB::table('price_data')
                    ->whereYear('tanggal',  $titik->year)
                    ->whereMonth('tanggal', $titik->month)
                    ->where('harga', '>', 0)->avg('harga');

                $hLaluAvg = DB::table('price_data')
                    ->whereYear('tanggal',  $titikLalu->year)
                    ->whereMonth('tanggal', $titikLalu->month)
                    ->where('harga', '>', 0)->avg('harga');

                $sparkDataBulanan[] = ($hIniAvg && $hLaluAvg && $hLaluAvg > 0)
                    ? round((($hIniAvg - $hLaluAvg) / $hLaluAvg) * 100, 2)
                    : 0.0;
            }

            $inflasiMtm = !empty($sparkDataBulanan) ? (float) end($sparkDataBulanan) : 0.0;

            $hIniYoy  = DB::table('price_data')
                ->whereYear('tanggal', $tahunFilter)->whereMonth('tanggal', $bulanFilter)
                ->where('harga', '>', 0)->avg('harga');
            $hLaluYoy = DB::table('price_data')
                ->whereYear('tanggal', $tahunFilter - 1)->whereMonth('tanggal', $bulanFilter)
                ->where('harga', '>', 0)->avg('harga');

            $inflasiYoy = ($hIniYoy && $hLaluYoy && $hLaluYoy > 0)
                ? round((($hIniYoy - $hLaluYoy) / $hLaluYoy) * 100, 2) : 0.0;

            $yoySparkData[$bulanFilter - 1] = $inflasiYoy;

            for ($m = 1; $m <= 12; $m++) {
                if ($m === $bulanFilter) continue;
                $hI = DB::table('price_data')
                    ->whereYear('tanggal', $tahunFilter)->whereMonth('tanggal', $m)
                    ->where('harga', '>', 0)->avg('harga');
                $hL = DB::table('price_data')
                    ->whereYear('tanggal', $tahunFilter - 1)->whereMonth('tanggal', $m)
                    ->where('harga', '>', 0)->avg('harga');
                $yoySparkData[$m - 1] = ($hI && $hL && $hL > 0)
                    ? round((($hI - $hL) / $hL) * 100, 2) : null;
            }
        } else {
            for ($m = 1; $m <= 12; $m++) {
                $prevTahun = ($m === 1) ? $tahunFilter - 1 : $tahunFilter;
                $prevBulan = ($m === 1) ? 12 : $m - 1;

                $hIniAvg  = DB::table('price_data')
                    ->whereYear('tanggal', $tahunFilter)->whereMonth('tanggal', $m)
                    ->where('harga', '>', 0)->avg('harga');
                $hLaluAvg = DB::table('price_data')
                    ->whereYear('tanggal', $prevTahun)->whereMonth('tanggal', $prevBulan)
                    ->where('harga', '>', 0)->avg('harga');

                $mom = ($hIniAvg && $hLaluAvg && $hLaluAvg > 0)
                    ? round((($hIniAvg - $hLaluAvg) / $hLaluAvg) * 100, 2) : null;

                $sparkDataTahunan[]   = $mom;
                $sparkLabelsTahunan[] = substr($bMap[$m] ?? 'Bln', 0, 3);

                $hI = DB::table('price_data')
                    ->whereYear('tanggal', $tahunFilter)->whereMonth('tanggal', $m)
                    ->where('harga', '>', 0)->avg('harga');
                $hL = DB::table('price_data')
                    ->whereYear('tanggal', $tahunFilter - 1)->whereMonth('tanggal', $m)
                    ->where('harga', '>', 0)->avg('harga');
                $yoySparkData[$m - 1] = ($hI && $hL && $hL > 0)
                    ? round((($hI - $hL) / $hL) * 100, 2) : null;
            }
        }

        return view('laporan.komoditas', compact(
            'data', 'analisis', 'daftarKomoditas', 'tahunTersedia',
            'tahunFilter', 'bulanFilter', 'varianFilter',
            'sparkDataBulanan', 'sparkDataTahunan', 'sparkLabelsTahunan',
            'inflasiMtm', 'inflasiYoy', 'inflasiYtd',
            'yoySparkData', 'yoySparkLabels', 'ihkForecast',
        ));
    }

    // =========================================================
    // CETAK
    // =========================================================
    public function cetak(Request $request)
    {
        return view('laporan.cetak', $this->buildExportData($request));
    }

    // =========================================================
    // EXPORT PDF
    // =========================================================
    public function exportPdf(Request $request)
    {
        $pdf = Pdf::loadView('laporan.cetak', $this->buildExportData($request))
                  ->setPaper('a4', 'landscape')
                  ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download('laporan-harga-' . now()->format('Ymd') . '.pdf');
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function exportCsv(Request $request)
    {
        $tahunFilter  = (int) ($request->tahun ?? date('Y'));
        $bulanFilter  = $request->filled('bulan')        ? (int) $request->bulan        : null;
        $komoditasId  = $request->filled('komoditas_id') ? (int) $request->komoditas_id : null;
        $varianFilter = $request->filled('varian')       ? $request->varian             : null;

        [$rows] = $this->buildRows($tahunFilter, $bulanFilter, $komoditasId, $varianFilter);

        $bMap = $this->namaBulanMap();

        if ($bulanFilter) {
            $tglIni = Carbon::create($tahunFilter, $bulanFilter, 1);
            $bLalu  = ($bMap[$tglIni->copy()->subMonth()->month] ?? '') . ' ' . $tglIni->copy()->subMonth()->year;
            $bIni   = ($bMap[$bulanFilter] ?? '') . ' ' . $tahunFilter;
            $bDepan = ($bMap[$tglIni->copy()->addMonth()->month] ?? '') . ' ' . $tglIni->copy()->addMonth()->year;
        } else {
            $bLalu  = '-';
            $bIni   = 'Rata-rata ' . $tahunFilter;
            $bDepan = 'Forecast ' . $tahunFilter;
        }

        $filename    = 'laporan-harga-' . now()->format('Ymd-His') . '.csv';
        $httpHeaders = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($rows, $bLalu, $bIni, $bDepan, $bulanFilter) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, ['Laporan Perbandingan Harga Komoditas — BPS Provinsi Riau']);
            fputcsv($out, ['Tanggal Cetak', now()->format('d/m/Y H:i')]);
            fputcsv($out, []);

            $cols = ['Komoditas', 'Varian'];
            if ($bulanFilter) $cols[] = "Harga {$bLalu} (Rp)";
            $cols[] = "Harga {$bIni} (Rp)";
            if ($bulanFilter) {
                $cols[] = 'Selisih MoM (Rp)';
                $cols[] = 'Perubahan MoM (%)';
                $cols[] = 'YtD (%)';
                $cols[] = 'YoY (%)';
            }
            $cols[] = 'IHK';
            $cols[] = 'RH';
            $cols[] = "Proyeksi Harga Prophet {$bIni} (Rp)";
            $cols[] = 'Tren Model';
            $cols[] = 'Status';
            fputcsv($out, $cols);

            foreach ($rows as $row) {
                $line = [$row->nama_komoditas, $row->nama_varian ?? '-'];

                if ($bulanFilter) {
                    $line[] = $row->harga_bulan_lalu
                        ? number_format($row->harga_bulan_lalu, 0, ',', '.') : '-';
                }

                $line[] = $row->harga_bulan_ini
                    ? number_format($row->harga_bulan_ini, 0, ',', '.') : '-';

                if ($bulanFilter) {
                    $line[] = $row->selisih_mom !== null
                        ? number_format($row->selisih_mom, 0, ',', '.') : '-';
                    $line[] = $row->persen_mom !== null
                        ? number_format($row->persen_mom, 2, ',', '.') . '%' : '-';
                    $line[] = $row->persen_ytd !== null
                        ? number_format($row->persen_ytd, 2, ',', '.') . '%' : '-';
                    $line[] = $row->persen_yoy !== null
                        ? number_format($row->persen_yoy, 2, ',', '.') . '%' : '-';
                }

                $line[] = $row->ihk !== null ? number_format($row->ihk, 2, ',', '.') : '-';
                $line[] = $row->rh  !== null ? number_format($row->rh,  2, ',', '.') : '-';
                $line[] = $row->harga_prediksi !== null
                    ? number_format($row->harga_prediksi, 0, ',', '.') : '-';
                $line[] = $row->tren_model ?? '-';
                $line[] = $this->statusLabel($row->status_mom);

                fputcsv($out, $line);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $httpHeaders);
    }

    // =========================================================
    // INTERNAL: Builder utama
    // =========================================================
    private function buildRows(
        int $tahunFilter,
        ?int $bulanFilter,
        ?int $komoditasId,
        ?string $varianFilter
    ): array {
        $threshold  = 0.005;
        $ctrInflasi = 0;
        $ctrDeflasi = 0;
        $ctrNaik    = 0;
        $ctrTurun   = 0;
        $ctrStabil  = 0;

        if ($bulanFilter) {
            $tglIni   = Carbon::create($tahunFilter, $bulanFilter, 1);
            $tglLalu  = $tglIni->copy()->subMonth();
            $tglDepan = $tglIni->copy()->addMonth();

            // Harga aktual bulan ini (fallback ke forecast jika bulan masa depan)
            $hIniMap = $this->queryAvgBulanan(
                $tglIni->year, $tglIni->month, $komoditasId, 'aktual', $varianFilter
            );
            $isFutureMonth = empty($hIniMap);
            if ($isFutureMonth) {
                $hIniMap = $this->queryAvgBulanan(
                    $tglIni->year, $tglIni->month, $komoditasId, 'forecast', $varianFilter
                );
            }

            // Harga aktual bulan lalu (fallback ke forecast)
            $hLaluMap = $this->queryAvgBulanan(
                $tglLalu->year, $tglLalu->month, $komoditasId, 'aktual', $varianFilter
            );
            if (empty($hLaluMap)) {
                $hLaluMap = $this->queryAvgBulanan(
                    $tglLalu->year, $tglLalu->month, $komoditasId, 'forecast', $varianFilter
                );
            }

            // Harga forecast bulan depan (untuk counter analisis proyeksi)
            $hDepanMap = $this->queryAvgBulanan(
                $tglDepan->year, $tglDepan->month, $komoditasId, 'forecast', $varianFilter
            );

            // ── Ambil semua IDs dari master ───────────────────────────────
            $masterIds = $this->fetchAllKomoditasIds($komoditasId, $varianFilter);
            $allIds    = $masterIds->merge(
                collect(array_keys($hLaluMap))
                    ->merge(array_keys($hIniMap))
                    ->merge(array_keys($hDepanMap))
            )->unique()->values();

            // ── FIX UTAMA: fetchNearestForecastPerKomoditas ───────────────
            // Menggantikan queryAvgBulanan(..., 'forecast', ...) yang hanya
            // cocok jika ada baris exact di price_forecasts untuk bulan tsb.
            // Versi baru mencari dengan strategi 3-level agar kolom tidak kosong.
            $hPrediksiMap = $this->fetchNearestForecastPerKomoditas(
                $tglIni->year,
                $tglIni->month,
                $allIds->toArray()
            );

            $ihkMap        = $this->fetchIhkPerKomoditas($tglIni->year, $tglIni->month);
            $hAwalTahunMap = $this->queryAvgBulanan($tglIni->year, 1, $komoditasId, 'aktual', $varianFilter);
            $hTahunLaluMap = $this->queryAvgBulanan($tglIni->year - 1, $tglIni->month, $komoditasId, 'aktual', $varianFilter);

            $detail = $this->fetchDetail($allIds);

            $rows = $allIds->map(function ($kId) use (
                $hLaluMap, $hIniMap, $hDepanMap, $hPrediksiMap,
                $ihkMap, $hAwalTahunMap, $hTahunLaluMap,
                $detail, $threshold, $isFutureMonth,
                &$ctrInflasi, &$ctrDeflasi, &$ctrNaik, &$ctrTurun, &$ctrStabil
            ) {
                $info      = $detail[$kId] ?? null;
                $hLalu     = isset($hLaluMap[$kId])    ? round((float) $hLaluMap[$kId])    : null;
                $hIni      = isset($hIniMap[$kId])     ? round((float) $hIniMap[$kId])     : null;
                $hDepan    = isset($hDepanMap[$kId])   ? round((float) $hDepanMap[$kId])   : null;
                $hPrediksi = isset($hPrediksiMap[$kId]) ? round((float) $hPrediksiMap[$kId]) : null;

                $selisihMom = ($hIni !== null && $hLalu !== null && $hLalu > 0)
                    ? $hIni - $hLalu : null;
                $persenMom  = ($selisihMom !== null && $hLalu > 0)
                    ? round(($selisihMom / $hLalu) * 100, 2) : null;

                // Status MtM
                if ($isFutureMonth) {
                    $statusMom = 'only-forecast';
                } elseif ($selisihMom !== null) {
                    $batas = $hLalu * $threshold;
                    if ($selisihMom > $batas)      { $statusMom = 'inflasi'; $ctrInflasi++; }
                    elseif ($selisihMom < -$batas) { $statusMom = 'deflasi'; $ctrDeflasi++; }
                    else                           { $statusMom = 'stabil'; }
                } else {
                    $statusMom = ($hDepan !== null || $hPrediksi !== null) ? 'only-forecast' : '';
                }

                // Counter proyeksi
                if ($hDepan !== null && $hIni !== null && $hIni > 0) {
                    $selDep = $hDepan - $hIni;
                    $basDep = $hIni * $threshold;
                    if ($selDep > $basDep)      $ctrNaik++;
                    elseif ($selDep < -$basDep) $ctrTurun++;
                    else                        $ctrStabil++;
                } elseif ($hDepan !== null && $hIni === null) {
                    $ctrNaik++;
                }

                // YtD
                $hAwalTahun = isset($hAwalTahunMap[$kId]) ? round((float) $hAwalTahunMap[$kId]) : null;
                $persenYtd  = ($hIni !== null && $hAwalTahun !== null && $hAwalTahun > 0)
                    ? round((($hIni - $hAwalTahun) / $hAwalTahun) * 100, 2) : null;

                // YoY
                $hTahunLalu = isset($hTahunLaluMap[$kId]) ? round((float) $hTahunLaluMap[$kId]) : null;
                $persenYoy  = ($hIni !== null && $hTahunLalu !== null && $hTahunLalu > 0)
                    ? round((($hIni - $hTahunLalu) / $hTahunLalu) * 100, 2) : null;

                // IHK & RH
                $ihkRow = $ihkMap[$kId] ?? null;
                $ihkVal = $ihkRow ? (float) $ihkRow->ihk : null;
                $rhVal  = $ihkRow ? (float) $ihkRow->rh  : null;
                if ($rhVal === null && $hIni !== null && $hLalu !== null && $hLalu > 0) {
                    $rhVal = round(($hIni / $hLalu) * 100, 4);
                }

                // Tren Model: prediksi vs harga bulan lalu
                $trenModel = null;
                if ($hPrediksi !== null && $hLalu !== null && $hLalu > 0) {
                    $selisihPrediksi = $hPrediksi - $hLalu;
                    $basPrediksi     = $hLalu * $threshold;
                    if ($selisihPrediksi > $basPrediksi)      $trenModel = 'naik';
                    elseif ($selisihPrediksi < -$basPrediksi) $trenModel = 'turun';
                    else                                       $trenModel = 'stabil';
                } elseif ($hPrediksi !== null && $hIni !== null && $hIni > 0) {
                    // Fallback: prediksi vs bulan ini jika bulan lalu tidak ada
                    $selisihPrediksi = $hPrediksi - $hIni;
                    $basPrediksi     = $hIni * $threshold;
                    if ($selisihPrediksi > $basPrediksi)      $trenModel = 'naik';
                    elseif ($selisihPrediksi < -$basPrediksi) $trenModel = 'turun';
                    else                                       $trenModel = 'stabil';
                }

                return $this->makeRow(
                    $kId, $info,
                    $hLalu, $hIni, $hDepan,
                    $selisihMom, $persenMom, $statusMom,
                    $ihkVal, $rhVal,
                    $persenYtd, $hAwalTahun,
                    $persenYoy, $hTahunLalu,
                    $hPrediksi, $trenModel
                );
            });

        } else {
            // ── MODE SEMUA BULAN ──────────────────────────────────────────
            $hAktualMap   = $this->queryAvgTahunan($tahunFilter, $komoditasId, 'aktual',   $varianFilter);
            $hForecastMap = $this->queryAvgTahunan($tahunFilter, $komoditasId, 'forecast', $varianFilter);

            $allIds = collect(
                array_keys($hAktualMap) + array_keys($hForecastMap)
            )->unique()->values();

            $detail = $this->fetchDetail($allIds);

            $rows = $allIds->map(function ($kId) use (
                $hAktualMap, $hForecastMap, $detail, $threshold,
                &$ctrNaik, &$ctrTurun, &$ctrStabil
            ) {
                $info      = $detail[$kId] ?? null;
                $hIni      = isset($hAktualMap[$kId])   ? round((float) $hAktualMap[$kId])   : null;
                $hDepan    = isset($hForecastMap[$kId]) ? round((float) $hForecastMap[$kId]) : null;
                $hPrediksi = $hDepan;

                $statusMom = ($hIni !== null)
                    ? 'stabil'
                    : (($hDepan !== null) ? 'only-forecast' : '');

                if ($hDepan !== null && $hIni !== null && $hIni > 0) {
                    $selDep = $hDepan - $hIni;
                    $basDep = $hIni * $threshold;
                    if ($selDep > $basDep)      $ctrNaik++;
                    elseif ($selDep < -$basDep) $ctrTurun++;
                    else                        $ctrStabil++;
                } elseif ($hDepan !== null && $hIni === null) {
                    $ctrNaik++;
                } else {
                    $ctrStabil++;
                }

                $trenModel = null;
                if ($hPrediksi !== null && $hIni !== null && $hIni > 0) {
                    $sel = $hPrediksi - $hIni;
                    $bas = $hIni * $threshold;
                    if ($sel > $bas)      $trenModel = 'naik';
                    elseif ($sel < -$bas) $trenModel = 'turun';
                    else                  $trenModel = 'stabil';
                }

                return $this->makeRow(
                    $kId, $info,
                    null, $hIni, $hDepan,
                    null, null, $statusMom,
                    null, null,
                    null, null,
                    null, null,
                    $hPrediksi, $trenModel
                );
            });
        }

        $order = ['inflasi' => 0, 'deflasi' => 1, 'stabil' => 2, 'only-forecast' => 3, '' => 4];
        $rows  = $rows->sort(fn($a, $b) =>
            ($order[$a->status_mom] ?? 9) <=> ($order[$b->status_mom] ?? 9)
        )->values();

        return [$rows, [
            'inflasi' => $ctrInflasi,
            'deflasi' => $ctrDeflasi,
            'naik'    => $ctrNaik,
            'turun'   => $ctrTurun,
            'stabil'  => $ctrStabil,
        ]];
    }

    // =========================================================
    // INTERNAL: Ambil harga prediksi dengan strategi 3-level
    //           (BARU — menggantikan queryAvgBulanan forecast)
    // =========================================================
    private function fetchNearestForecastPerKomoditas(
        int $tahun,
        int $bulan,
        array $komoditasIds
    ): array {
        if (empty($komoditasIds)) return [];

        $target = Carbon::create($tahun, $bulan, 1);

        // ── Level 1: Exact match bulan & tahun ───────────────────────────
        $exact = DB::table('price_forecasts')
            ->whereIn('komoditas_id', $komoditasIds)
            ->whereYear('tanggal',  $tahun)
            ->whereMonth('tanggal', $bulan)
            ->whereNotNull('harga_prediksi')
            ->where('harga_prediksi', '>', 0)
            ->selectRaw('komoditas_id, AVG(harga_prediksi) as avg_pred')
            ->groupBy('komoditas_id')
            ->pluck('avg_pred', 'komoditas_id')
            ->toArray();

        $missing = array_diff($komoditasIds, array_keys($exact));
        if (empty($missing)) return $exact;

        // ── Level 2: Entri terdekat dalam ±6 bulan ───────────────────────
        $lower = $target->copy()->subMonths(6)->format('Y-m-d');
        $upper = $target->copy()->addMonths(6)->format('Y-m-d');

        $nearby = DB::table('price_forecasts')
            ->whereIn('komoditas_id', $missing)
            ->whereBetween('tanggal', [$lower, $upper])
            ->whereNotNull('harga_prediksi')
            ->where('harga_prediksi', '>', 0)
            ->select('komoditas_id', 'tanggal', 'harga_prediksi')
            ->get();

        $nearbyBest = [];
        foreach ($nearby as $row) {
            $diff = abs(Carbon::parse($row->tanggal)->diffInDays($target));
            $kid  = $row->komoditas_id;
            if (!isset($nearbyBest[$kid]) || $diff < $nearbyBest[$kid]['diff']) {
                $nearbyBest[$kid] = ['pred' => (float) $row->harga_prediksi, 'diff' => $diff];
            }
        }

        $result = $exact;
        foreach ($nearbyBest as $kid => $v) {
            $result[$kid] = $v['pred'];
        }

        // ── Level 3: Rata-rata semua forecast yang ada (last resort) ──────
        $stillMissing = array_diff($missing, array_keys($nearbyBest));
        if (!empty($stillMissing)) {
            $fallback = DB::table('price_forecasts')
                ->whereIn('komoditas_id', $stillMissing)
                ->whereNotNull('harga_prediksi')
                ->where('harga_prediksi', '>', 0)
                ->selectRaw('komoditas_id, AVG(harga_prediksi) as avg_pred')
                ->groupBy('komoditas_id')
                ->pluck('avg_pred', 'komoditas_id')
                ->toArray();

            foreach ($fallback as $kid => $pred) {
                $result[$kid] = (float) $pred;
            }
        }

        return $result;
    }

    // =========================================================
    // INTERNAL: Ambil data IHK dari Flask
    // =========================================================
    private function getIhkData(): array
    {
        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:5000/api/ihk/summary');
            if ($response->successful()) {
                return $response->json('data') ?? [];
            }
        } catch (\Exception $e) {
            \Log::warning('Flask IHK tidak tersedia: ' . $e->getMessage());
        }
        return [];
    }

    // =========================================================
    // INTERNAL: Ambil IHK & RH per-komoditas dari DB
    // =========================================================
    private function fetchIhkPerKomoditas(int $tahun, int $bulan): \Illuminate\Support\Collection
    {
        return DB::table('andil_inflasi_bulanan')
            ->whereYear('tanggal',  $tahun)
            ->whereMonth('tanggal', $bulan)
            ->select('komoditas_id', 'nilai_ihk_komoditas as ihk', 'nilai_rh as rh')
            ->get()
            ->keyBy('komoditas_id');
    }

    // =========================================================
    // INTERNAL: AVG harga per BULAN (aktual atau forecast)
    // =========================================================
    private function queryAvgBulanan(
        int $tahun,
        int $bulan,
        ?int $kId,
        string $tipe,
        ?string $varianFilter
    ): array {
        [$table, $priceCol] = $tipe === 'forecast'
            ? ['price_forecasts', 'harga_prediksi']
            : ['price_data',      'harga'];

        $q = DB::table($table)
            ->selectRaw("komoditas_id, AVG({$priceCol}) as avg_harga")
            ->whereYear('tanggal',  $tahun)
            ->whereMonth('tanggal', $bulan)
            ->whereNotNull($priceCol)
            ->where($priceCol, '>', 0);

        if ($varianFilter) {
            $q->whereExists(function ($sub) use ($varianFilter, $table) {
                $sub->select(DB::raw(1))
                    ->from('master_komoditas')
                    ->whereColumn('master_komoditas.id', "{$table}.komoditas_id")
                    ->where('master_komoditas.nama_varian', $varianFilter);
            });
        }

        if ($kId) $q->where('komoditas_id', $kId);

        return $q->groupBy('komoditas_id')
                 ->pluck('avg_harga', 'komoditas_id')
                 ->toArray();
    }

    // =========================================================
    // INTERNAL: AVG harga SELURUH TAHUN (mode semua bulan)
    // =========================================================
    private function queryAvgTahunan(
        int $tahun,
        ?int $kId,
        string $tipe,
        ?string $varianFilter
    ): array {
        [$table, $priceCol] = $tipe === 'forecast'
            ? ['price_forecasts', 'harga_prediksi']
            : ['price_data',      'harga'];

        $q = DB::table($table)
            ->selectRaw("komoditas_id, AVG({$priceCol}) as avg_harga")
            ->whereYear('tanggal', $tahun)
            ->whereNotNull($priceCol)
            ->where($priceCol, '>', 0);

        if ($varianFilter) {
            $q->whereExists(function ($sub) use ($varianFilter, $table) {
                $sub->select(DB::raw(1))
                    ->from('master_komoditas')
                    ->whereColumn('master_komoditas.id', "{$table}.komoditas_id")
                    ->where('master_komoditas.nama_varian', $varianFilter);
            });
        }

        if ($kId) $q->where('komoditas_id', $kId);

        return $q->groupBy('komoditas_id')
                 ->pluck('avg_harga', 'komoditas_id')
                 ->toArray();
    }

    // =========================================================
    // INTERNAL: Ambil semua ID dari master_komoditas
    // =========================================================
    private function fetchAllKomoditasIds(?int $komoditasId, ?string $varianFilter): \Illuminate\Support\Collection
    {
        $q = DB::table('master_komoditas')->select('id');
        if ($komoditasId) $q->where('id', $komoditasId);
        if ($varianFilter) $q->where('nama_varian', $varianFilter);
        return $q->pluck('id');
    }

    // =========================================================
    // INTERNAL: Ambil detail master_komoditas
    // =========================================================
    private function fetchDetail(\Illuminate\Support\Collection $ids): \Illuminate\Support\Collection
    {
        if ($ids->isEmpty()) return collect();
        return DB::table('master_komoditas')
            ->whereIn('id', $ids->all())
            ->select('id', 'nama_komoditas', 'nama_varian')
            ->get()->keyBy('id');
    }

    // =========================================================
    // INTERNAL: Buat object row standar
    // =========================================================
    private function makeRow(
        int     $kId,
        mixed   $info,
        ?int    $hLalu,
        ?int    $hIni,
        ?int    $hDepan,
        ?float  $selisihMom,
        ?float  $persenMom,
        string  $statusMom,
        ?float  $ihk        = null,
        ?float  $rh         = null,
        ?float  $persenYtd  = null,
        ?int    $hAwalTahun = null,
        ?float  $persenYoy  = null,
        ?int    $hTahunLalu = null,
        ?int    $hPrediksi  = null,
        ?string $trenModel  = null
    ): object {
        return (object) [
            'komoditas_id'      => $kId,
            'nama_komoditas'    => $info->nama_komoditas ?? '-',
            'nama_varian'       => $info->nama_varian    ?? null,
            'harga_bulan_lalu'  => $hLalu,
            'harga_bulan_ini'   => $hIni,
            'harga_bulan_depan' => $hDepan,
            'selisih_mom'       => $selisihMom,
            'persen_mom'        => $persenMom,
            'status_mom'        => $statusMom,
            'ihk'               => $ihk,
            'rh'                => $rh,
            'ihk_change'        => null,
            'persen_ytd'        => $persenYtd,
            'harga_awal_tahun'  => $hAwalTahun,
            'persen_yoy'        => $persenYoy,
            'harga_tahun_lalu'  => $hTahunLalu,
            'harga_prediksi'    => $hPrediksi,
            'tren_model'        => $trenModel,
        ];
    }

    // =========================================================
    // INTERNAL: Data untuk cetak / PDF
    // =========================================================
    private function buildExportData(Request $request): array
    {
        $tahunFilter  = (int) ($request->tahun ?? date('Y'));
        $bulanFilter  = $request->filled('bulan')        ? (int) $request->bulan        : null;
        $komoditasId  = $request->filled('komoditas_id') ? (int) $request->komoditas_id : null;
        $varianFilter = $request->filled('varian')       ? $request->varian             : null;

        [$rows, $analisis] = $this->buildRows($tahunFilter, $bulanFilter, $komoditasId, $varianFilter);

        $bMap    = $this->namaBulanMap();
        $tanggal = $bulanFilter
            ? (($bMap[$bulanFilter] ?? '') . ' ' . $tahunFilter)
            : ('Semua Bulan — ' . $tahunFilter);

        return compact('rows', 'tanggal', 'analisis', 'bulanFilter', 'tahunFilter');
    }

    // =========================================================
    // INTERNAL: Map nama bulan Indonesia
    // =========================================================
    private function namaBulanMap(): array
    {
        return [
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember',
        ];
    }

    // =========================================================
    // INTERNAL: Label status untuk CSV export
    // =========================================================
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'inflasi'       => 'Harga Naik',
            'deflasi'       => 'Harga Turun',
            'stabil'        => 'Stabil',
            'only-forecast' => 'Proyeksi Saja',
            default         => '-',
        };
    }
}