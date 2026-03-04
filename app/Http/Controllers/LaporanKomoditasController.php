<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKomoditas;
use Carbon\Carbon;

class LaporanKomoditasController extends Controller
{
    public function index(Request $request)
    {
        // 1. Ambil list tahun dari kedua tabel untuk dropdown filter
        $tahunAktual   = DB::table('price_data')->selectRaw('YEAR(tanggal) as tahun')->distinct()->pluck('tahun');
        $tahunForecast = DB::table('price_forecasts')->selectRaw('YEAR(tanggal) as tahun')->distinct()->pluck('tahun');

        $tahunTersedia = $tahunAktual->merge($tahunForecast)->unique()->sortDesc()->values();

        $tahunMax = $tahunTersedia->first() ?? date('Y');
        $tahunMin = $tahunTersedia->last()  ?? date('Y') - 5;

        // 2. Tangkap filter dari Request
        $tahun        = $request->tahun        ?? $tahunMax;
        $bulan        = $request->bulan;
        $minggu       = $request->minggu;
        $komoditas_id = $request->komoditas_id;

        // 3. Ambil daftar komoditas untuk dropdown
        $daftarKomoditas = MasterKomoditas::orderBy('nama_komoditas')->get();

        // 4. Jalankan Query Utama
        $query = $this->getQueryLaporan($tahun, $bulan, $minggu, $komoditas_id);

        // Kloning query untuk Ringkasan Analisis (sebelum dipaginasi)
        $allDataForAnalisis = (clone $query)->get();
        $analisis           = $this->analisisDeskriptif($allDataForAnalisis);

        // Ambil data dengan pagination
        $data = $query->paginate(15)->withQueryString();

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
    // QUERY UTAMA
    // Fix duplikat: gunakan subquery GROUP BY pada price_data
    // dan price_forecasts sebelum di-JOIN
    // =========================================================
    private function getQueryLaporan($tahun, $bulan = null, $minggu = null, $komoditas_id = null)
    {
        $tahun = (int) $tahun;

        // Subquery price_data: rata-rata harga per (komoditas_id, tanggal)
        // untuk menghilangkan duplikat baris pada tanggal yang sama
        $pdSub = "
            SELECT komoditas_id, tanggal, AVG(harga) as harga
            FROM price_data
            WHERE YEAR(tanggal) = {$tahun}
            GROUP BY komoditas_id, tanggal
        ";

        // Subquery price_forecasts: 1 baris per (komoditas_id, tanggal)
        $pfSub = "
            SELECT komoditas_id, tanggal,
                   AVG(harga_prediksi) as harga_prediksi,
                   AVG(harga_lower)    as harga_lower,
                   AVG(harga_upper)    as harga_upper
            FROM price_forecasts
            WHERE YEAR(tanggal) = {$tahun}
            GROUP BY komoditas_id, tanggal
        ";

        // Subquery tanggal unik gabungan dari kedua tabel
        $distinctSql = "
            SELECT DISTINCT komoditas_id, tanggal
            FROM (
                SELECT komoditas_id, tanggal FROM price_data      WHERE YEAR(tanggal) = {$tahun}
                UNION
                SELECT komoditas_id, tanggal FROM price_forecasts WHERE YEAR(tanggal) = {$tahun}
            ) as _union_raw
        ";

        $query = DB::table(DB::raw("({$distinctSql}) as all_dates"))
            ->select(
                'all_dates.komoditas_id',
                'mk.nama_komoditas',
                'mk.nama_varian',
                'all_dates.tanggal',
                'pd_agg.harga               as harga_aktual',
                DB::raw('pf_agg.harga_prediksi  as harga_prediksi'),
                DB::raw('pf_agg.harga_lower     as harga_lower'),
                DB::raw('pf_agg.harga_upper     as harga_upper')
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

        // ── Filter tambahan ──
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
            $threshold = $aktual * 0.001; // toleransi 0.1%

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
}