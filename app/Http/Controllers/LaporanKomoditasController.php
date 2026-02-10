<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PriceData;
use App\Models\MasterKomoditas;
use App\Models\PriceForecast; 
use Carbon\Carbon;

class LaporanKomoditasController extends Controller
{
    /**
     * Menampilkan halaman laporan utama
     */
    public function index(Request $request)
    {
        // Ambil filter dari request
        $tahun = $request->tahun ?? date('Y');
        $bulan = $request->bulan;
        $minggu = $request->minggu;
        $komoditas_id = $request->komoditas_id;

        // 1. Ambil daftar komoditas untuk dropdown filter
        $daftarKomoditas = MasterKomoditas::orderBy('nama_komoditas')->get();

        // 2. Query Utama Laporan dengan Filter Periodik
        $query = $this->getQueryLaporan($tahun, $bulan, $minggu, $komoditas_id);
        
        // Ambil semua data untuk perhitungan statistik/analisis
        $allDataForAnalisis = (clone $query)->get();
        $analisis = $this->analisisDeskriptif($allDataForAnalisis);
        
        // Paginate data untuk tabel (Gunakan withQueryString agar filter tidak hilang saat ganti halaman)
        $data = $query->paginate(10)->withQueryString();

        return view('laporan.komoditas', compact(
            'data', 
            'daftarKomoditas', 
            'analisis'
        ));
    }

    /**
     * Query yang sudah diperbaiki untuk mendukung Filter Tahun, Bulan, dan Minggu
     */
    private function getQueryLaporan($tahun, $bulan = null, $minggu = null, $komoditas_id = null)
    {
        $query = DB::table('master_komoditas')
            // Join ke data aktual
            ->leftJoin('price_data', 'master_komoditas.id', '=', 'price_data.komoditas_id')
            // Join ke data prediksi berdasarkan komoditas dan tanggal yang sama
            ->leftJoin('price_forecasts', function($join) {
                $join->on('master_komoditas.id', '=', 'price_forecasts.komoditas_id')
                     ->on('price_data.tanggal', '=', 'price_forecasts.tanggal');
            })
            ->select(
                'master_komoditas.id as id_komoditas',
                'master_komoditas.nama_komoditas',
                'master_komoditas.nama_varian',
                'price_data.tanggal',
                'price_data.harga as harga_aktual',
                'price_forecasts.yhat as harga_prediksi'
            );

        // --- Logic Filter ---

        // 1. Filter Tahun (Wajib ada)
        $query->whereYear('price_data.tanggal', $tahun);

        // 2. Filter Bulan (Opsional)
        if ($bulan) {
            $query->whereMonth('price_data.tanggal', $bulan);
        }

        // 3. Filter Minggu (Opsional)
        // Menggunakan formula: Minggu ke = (Hari - 1) / 7 + 1
        if ($minggu) {
            $query->whereRaw('FLOOR((DAY(price_data.tanggal) - 1) / 7) + 1 = ?', [$minggu]);
        }

        // 4. Filter Komoditas (Opsional)
        if ($komoditas_id) {
            $query->where('master_komoditas.id', $komoditas_id);
        }

        // Hapus data yang tidak punya tanggal aktual (pembersihan hasil left join)
        $query->whereNotNull('price_data.tanggal');

        return $query->orderBy('price_data.tanggal', 'desc');
    }

    /**
     * Analisis Deskriptif (Tetap sama, memastikan data valid)
     */
    private function analisisDeskriptif($data)
    {
        $naik = 0; $turun = 0; $stabil = 0;

        foreach ($data as $item) {
            $aktual = (float) ($item->harga_aktual ?? 0);
            $prediksi = (float) ($item->harga_prediksi ?? 0);

            if ($aktual > 0 && $prediksi > 0) {
                if ($prediksi > $aktual) {
                    $naik++;
                } elseif ($prediksi < $aktual) {
                    $turun++;
                } else {
                    $stabil++;
                }
            }
        }

        return [
            'naik' => $naik,
            'turun' => $turun,
            'stabil' => $stabil,
            'kesimpulan' => $this->generateKesimpulan($naik, $turun, $stabil)
        ];
    }

    private function generateKesimpulan($naik, $turun, $stabil)
    {
        if ($naik == 0 && $turun == 0 && $stabil == 0) return "Data tidak tersedia.";
        if ($naik > $turun) return "Tren harga cenderung mengalami kenaikan.";
        if ($turun > $naik) return "Tren harga cenderung mengalami penurunan.";
        return "Harga cenderung stabil.";
    }

    /**
     * Cetak Laporan (Disesuaikan agar filter ikut terbawa)
     */
    public function cetak(Request $request)
    {
        $tahun = $request->tahun ?? date('Y');
        $bulan = $request->bulan;
        $minggu = $request->minggu;
        $komoditas_id = $request->komoditas_id;
        
        $data = $this->getQueryLaporan($tahun, $bulan, $minggu, $komoditas_id)->get();
        $analisis = $this->analisisDeskriptif($data);

        return view('laporan.cetak', compact('data', 'analisis'));
    }
}