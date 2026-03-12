<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKomoditas;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanKomoditasController extends Controller
{
    // =========================================================
    // INDEX — Halaman utama dengan tabel + pagination
    // =========================================================
    public function index(Request $request)
    {
        $tahunCeiling = (int) date('Y') + 2;

        // Ambil tahun yang BENAR-BENAR ada datanya — tidak pakai ceiling filter
        // agar tahun kosong seperti 2025/2026 tidak masuk dropdown
        $tahunAktual = DB::table('price_data')
            ->selectRaw('YEAR(tanggal) as tahun')
            ->distinct()
            ->pluck('tahun');

        $tahunForecast = DB::table('price_forecasts')
            ->selectRaw('YEAR(tanggal) as tahun')
            ->distinct()
            ->pluck('tahun');

        $tahunTersedia = $tahunAktual->merge($tahunForecast)->unique()->sortDesc()->values();

        $tahunMax = $tahunTersedia->first() ?? date('Y');
        $tahunMin = $tahunTersedia->last()  ?? (int) date('Y') - 5;

        // Default ke tahun terbaru yang ada datanya, bukan tahun sekarang
        $tahunInput = (int) ($request->tahun ?? $tahunMax);
        $tahun      = $request->has('tahun') ? $tahunInput : $tahunMax;

        $bulan        = $request->bulan;
        $minggu       = $request->minggu;
        $komoditas_id = $request->komoditas_id;

        $daftarKomoditas = MasterKomoditas::orderBy('nama_komoditas')->get();

        $query              = $this->getQueryLaporan($tahun, $bulan, $minggu, $komoditas_id);
        $allDataForAnalisis = (clone $query)->get();
        $analisis           = $this->analisisDeskriptif($allDataForAnalisis);
        $data               = $query->paginate(15)->withQueryString();

        return view('laporan.komoditas', compact(
            'data',
            'daftarKomoditas',
            'analisis',
            'tahunTersedia',
            'tahun',
            'tahunMax',
            'tahunMin'
        ));
    }

    // =========================================================
    // CETAK — Tampilan print browser (window.print)
    // =========================================================
    public function cetak(Request $request)
    {
        $tahunCeiling = (int) date('Y') + 2;
        $tahun        = min((int) ($request->tahun ?? date('Y')), $tahunCeiling);
        $bulan        = $request->bulan;
        $minggu       = $request->minggu;
        $komoditas_id = $request->komoditas_id;

        $data     = $this->getQueryLaporan($tahun, $bulan, $minggu, $komoditas_id)->get();
        $analisis = $this->analisisDeskriptif($data);
        $tanggal  = $this->formatTanggalLabel($tahun, $bulan);

        return view('laporan.cetak', compact('data', 'analisis', 'tanggal'));
    }

    // =========================================================
    // EXPORT PDF — Download PDF via DomPDF
    // =========================================================
    public function exportPdf(Request $request)
    {
        $tahunCeiling = (int) date('Y') + 2;
        $tahun        = min((int) ($request->tahun ?? date('Y')), $tahunCeiling);
        $bulan        = $request->bulan;
        $minggu       = $request->minggu;
        $komoditas_id = $request->komoditas_id;

        $data     = $this->getQueryLaporan($tahun, $bulan, $minggu, $komoditas_id)->get();
        $analisis = $this->analisisDeskriptif($data);
        $tanggal  = $this->formatTanggalLabel($tahun, $bulan);

        $pdf = Pdf::loadView('laporan.cetak', compact('data', 'analisis', 'tanggal'))
                  ->setPaper('a4', 'landscape')
                  ->setOptions([
                      'defaultFont'         => 'sans-serif',
                      'isHtml5ParserEnabled' => true,
                      'isRemoteEnabled'      => false,
                  ]);

        $filename = 'laporan-komoditas-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================
    // EXPORT CSV — Download CSV via stream response
    // =========================================================
    public function exportCsv(Request $request)
    {
        $tahunCeiling = (int) date('Y') + 2;
        $tahun        = min((int) ($request->tahun ?? date('Y')), $tahunCeiling);
        $bulan        = $request->bulan;
        $minggu       = $request->minggu;
        $komoditas_id = $request->komoditas_id;

        $data    = $this->getQueryLaporan($tahun, $bulan, $minggu, $komoditas_id)->get();
        $tanggal = $this->formatTanggalLabel($tahun, $bulan);

        $filename = 'laporan-komoditas-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($data, $tanggal) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['Laporan Analisis Harga Komoditas - BPS Provinsi Riau']);
            fputcsv($handle, ['Periode', $tanggal ?? 'Semua Tanggal']);
            fputcsv($handle, ['Tanggal Cetak', now()->format('d/m/Y H:i')]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'No', 'Tanggal', 'Komoditas', 'Varian',
                'Harga Aktual (Rp)', 'Harga Prediksi (Rp)',
                'Batas Bawah (Rp)', 'Batas Atas (Rp)',
                'Selisih (Rp)', 'Tren',
            ]);

            foreach ($data as $index => $item) {
                $aktual   = (float) ($item->harga_aktual   ?? 0);
                $prediksi = (float) ($item->harga_prediksi ?? 0);
                $lower    = (float) ($item->harga_lower    ?? 0);
                $upper    = (float) ($item->harga_upper    ?? 0);
                $selisih  = $prediksi - $aktual;

                if ($prediksi > 0 && $aktual > 0) {
                    $threshold = $aktual * 0.001;
                    if ($selisih > $threshold)      $tren = 'Naik';
                    elseif ($selisih < -$threshold) $tren = 'Turun';
                    else                            $tren = 'Stabil';
                } elseif ($prediksi > 0) {
                    $tren = 'Proyeksi';
                } else {
                    $tren = '-';
                }

                fputcsv($handle, [
                    $index + 1,
                    Carbon::parse($item->tanggal)->format('d/m/Y'),
                    $item->nama_komoditas,
                    $item->nama_varian ?? '-',
                    $aktual   > 0 ? number_format($aktual,   0, ',', '.') : '-',
                    $prediksi > 0 ? number_format($prediksi, 0, ',', '.') : '-',
                    $lower    > 0 ? number_format($lower,    0, ',', '.') : '-',
                    $upper    > 0 ? number_format($upper,    0, ',', '.') : '-',
                    $selisih != 0 ? number_format(abs($selisih), 0, ',', '.') : '-',
                    $tren,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // =========================================================
    // QUERY UTAMA
    // =========================================================
    private function getQueryLaporan($tahun, $bulan = null, $minggu = null, $komoditas_id = null)
    {
        $tahun        = (int) $tahun;
        $tahunCeiling = (int) date('Y') + 2;

        if ($tahun > $tahunCeiling) {
            $tahun = $tahunCeiling;
        }

        $pdSub = "
            SELECT komoditas_id, tanggal, AVG(harga) as harga
            FROM price_data
            WHERE YEAR(tanggal) = {$tahun}
            GROUP BY komoditas_id, tanggal
        ";

        $pfSub = "
            SELECT komoditas_id, tanggal,
                   AVG(harga_prediksi) as harga_prediksi,
                   AVG(harga_lower)    as harga_lower,
                   AVG(harga_upper)    as harga_upper
            FROM price_forecasts
            WHERE YEAR(tanggal) = {$tahun}
              AND YEAR(tanggal) <= {$tahunCeiling}
            GROUP BY komoditas_id, tanggal
        ";

        $distinctSql = "
            SELECT DISTINCT komoditas_id, tanggal
            FROM (
                SELECT komoditas_id, tanggal
                FROM price_data
                WHERE YEAR(tanggal) = {$tahun}

                UNION

                SELECT komoditas_id, tanggal
                FROM price_forecasts
                WHERE YEAR(tanggal) = {$tahun}
                  AND YEAR(tanggal) <= {$tahunCeiling}
            ) as _union_raw
        ";

        $query = DB::table(DB::raw("({$distinctSql}) as all_dates"))
            ->select(
                'all_dates.komoditas_id',
                'mk.nama_komoditas',
                'mk.nama_varian',
                'all_dates.tanggal',
                'pd_agg.harga             as harga_aktual',
                DB::raw('pf_agg.harga_prediksi as harga_prediksi'),
                DB::raw('pf_agg.harga_lower    as harga_lower'),
                DB::raw('pf_agg.harga_upper    as harga_upper')
            )
            ->join('master_komoditas as mk', 'all_dates.komoditas_id', '=', 'mk.id')
            ->leftJoin(DB::raw("({$pdSub}) as pd_agg"), function ($join) {
                $join->on('all_dates.komoditas_id', '=', 'pd_agg.komoditas_id')
                     ->on('all_dates.tanggal',      '=', 'pd_agg.tanggal');
            })
            ->leftJoin(DB::raw("({$pfSub}) as pf_agg"), function ($join) {
                $join->on('all_dates.komoditas_id', '=', 'pf_agg.komoditas_id')
                     ->on('all_dates.tanggal',      '=', 'pf_agg.tanggal');
            });

        if ($bulan) {
            $query->whereMonth('all_dates.tanggal', (int) $bulan);
        }

        if ($minggu) {
            $query->whereRaw('CEIL(DAY(all_dates.tanggal) / 7) = ?', [(int) $minggu]);
        }

        if ($komoditas_id) {
            $query->where('all_dates.komoditas_id', (int) $komoditas_id);
        }

        return $query
            ->orderBy('all_dates.tanggal', 'desc')
            ->orderBy('mk.nama_komoditas', 'asc');
    }

    // =========================================================
    // ANALISIS DESKRIPTIF
    // =========================================================
    private function analisisDeskriptif($data)
    {
        if ($data->isEmpty()) {
            return [
                'naik'       => 0,
                'turun'      => 0,
                'stabil'     => 0,
                'kesimpulan' => 'Tidak ada data yang tersedia untuk periode yang dipilih.',
            ];
        }

        $naik = 0; $turun = 0; $stabil = 0;

        foreach ($data as $row) {
            $aktual   = (float) ($row->harga_aktual   ?? 0);
            $prediksi = (float) ($row->harga_prediksi ?? 0);

            if ($aktual <= 0 || $prediksi <= 0) {
                $stabil++;
                continue;
            }

            $diff      = $prediksi - $aktual;
            $threshold = $aktual * 0.001;

            if ($diff > $threshold)      $naik++;
            elseif ($diff < -$threshold) $turun++;
            else                         $stabil++;
        }

        $total = $naik + $turun + $stabil;

        if ($total === 0) {
            $kesimpulan = 'Data belum mencukupi untuk melakukan analisis deskriptif.';
        } elseif ($naik > $turun && $naik > $stabil) {
            $kesimpulan = 'Sebagian besar komoditas diprediksi mengalami kenaikan harga. Mohon pantau ketersediaan stok di pasar.';
        } elseif ($turun > $naik && $turun > $stabil) {
            $kesimpulan = 'Tren harga menunjukkan penurunan pada mayoritas komoditas. Daya beli masyarakat diprediksi tetap terjaga.';
        } else {
            $kesimpulan = 'Secara keseluruhan, harga komoditas pada periode ini terpantau stabil.';
        }

        return compact('naik', 'turun', 'stabil', 'kesimpulan');
    }

    // =========================================================
    // HELPER — Format label periode
    // =========================================================
    private function formatTanggalLabel($tahun, $bulan = null): ?string
    {
        if ($bulan && $tahun) {
            return Carbon::createFromDate($tahun, $bulan, 1)->translatedFormat('F Y');
        }
        return $tahun ? 'Tahun ' . $tahun : null;
    }
}