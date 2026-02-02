<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Komoditas; 

class LaporanKomoditasController extends Controller
{
    /**
     * Menampilkan halaman laporan utama dengan filter dan pagination
     */
    public function index(Request $request)
    {
        $tanggal = $request->tanggal;
        $komoditas_id = $request->komoditas_id;

        // Ambil daftar komoditas untuk dropdown filter
        $daftarKomoditas = DB::table('master_komoditas')
            ->select('id', 'nama_komoditas', 'nama_varian')
            ->orderBy('nama_komoditas')
            ->get();

        // Ambil Query dasar
        $query = $this->getQueryLaporan($tanggal, $komoditas_id);

        // Untuk Analisis Deskriptif, kita butuh semua data tanpa pagination
        $allDataForAnalisis = (clone $query)->get();
        $analisis = $this->analisisDeskriptif($allDataForAnalisis);

        // Untuk tabel, kita gunakan pagination (10 data per halaman)
        $data = $query->paginate(10)->withQueryString();

        return view('laporan.komoditas', compact('data', 'tanggal', 'komoditas_id', 'daftarKomoditas', 'analisis'));
    }

    /**
     * Menampilkan halaman untuk cetak laporan (Tanpa Pagination)
     */
    public function cetak(Request $request)
    {
        $tanggal = $request->tanggal;
        $komoditas_id = $request->komoditas_id;

        $data = $this->getQueryLaporan($tanggal, $komoditas_id)->get();
        $analisis = $this->analisisDeskriptif($data);

        return view('laporan.cetak', compact('data', 'tanggal', 'analisis'));
    }

    /**
     * Query pusat untuk mengambil data laporan
     */
    private function getQueryLaporan($tanggal = null, $komoditas_id = null)
    {
        $query = DB::table('price_data')
            ->join('master_komoditas', 'price_data.komoditas_id', '=', 'master_komoditas.id')
            ->leftJoin('price_forecasts', function($join) {
                $join->on('price_data.komoditas_id', '=', 'price_forecasts.komoditas_id')
                     ->on('price_data.tanggal', '=', 'price_forecasts.tanggal');
            })
            ->select(
                'price_data.tanggal',
                'master_komoditas.nama_komoditas',
                'master_komoditas.nama_varian',
                'price_data.harga as harga_aktual',
                'price_forecasts.yhat as harga_prediksi' // yhat berasal dari model Prophet
            );

        // Tambahkan Filter jika ada
        if ($tanggal) {
            $query->whereDate('price_data.tanggal', $tanggal);
        }

        if ($komoditas_id) {
            $query->where('price_data.komoditas_id', $komoditas_id);
        }

        return $query->orderBy('price_data.tanggal', 'desc');
    }

    /**
     * Menghitung statistik sederhana dari data
     */
    private function analisisDeskriptif($data)
    {
        $naik = 0;
        $turun = 0;
        $stabil = 0;

        foreach ($data as $item) {
            $h_prediksi = $item->harga_prediksi ?? 0;
            $h_aktual = $item->harga_aktual ?? 0;

            if ($h_prediksi > $h_aktual) {
                $naik++;
            } elseif ($h_prediksi < $h_aktual && $h_prediksi > 0) {
                $turun++;
            } else {
                $stabil++;
            }
        }

        return [
            'total' => $data->count(),
            'naik' => $naik,
            'turun' => $turun,
            'stabil' => $stabil,
            'kesimpulan' => $this->generateKesimpulan($naik, $turun, $stabil)
        ];
    }

    private function generateKesimpulan($naik, $turun, $stabil)
    {
        if ($naik == 0 && $turun == 0 && $stabil == 0) {
            return "Belum ada data tersedia untuk periode ini.";
        }

        if ($naik > $turun) {
            return "Tren menunjukkan potensi kenaikan harga pada mayoritas komoditas terpilih.";
        } elseif ($turun > $naik) {
            return "Sebagian besar komoditas diprediksi mengalami penurunan harga.";
        }

        return "Harga komoditas cenderung stabil pada periode yang dipilih.";
    }
}