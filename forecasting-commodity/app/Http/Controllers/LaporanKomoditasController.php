<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PriceData;
use App\Models\MasterKomoditas;
use Carbon\Carbon;

class LaporanKomoditasController extends Controller
{
    public function index(Request $request)
    {
        $daftarKomoditas = MasterKomoditas::orderBy('nama_komoditas')->get();

        // Ambil range tahun dari data yang ada di DB
        $tahunMin = PriceData::selectRaw('YEAR(MIN(tanggal)) as tahun_min')->value('tahun_min') ?? 2020;
        $tahunMax = PriceData::selectRaw('YEAR(MAX(tanggal)) as tahun_max')->value('tahun_max') ?? date('Y');

        // Default tahun = tahun terbaru yang ada data
        $tahun        = $request->tahun ?? $tahunMax;
        $bulan        = $request->bulan;
        $minggu       = $request->minggu;
        $komoditas_id = $request->komoditas_id;

        $query              = $this->getQueryLaporan($tahun, $bulan, $minggu, $komoditas_id);
        $allDataForAnalisis = (clone $query)->get();
        $analisis           = $this->analisisDeskriptif($allDataForAnalisis);
        $data               = $query->paginate(20)->withQueryString();

        return view('laporan.komoditas', compact(
            'data', 'daftarKomoditas', 'analisis',
            'tahun', 'tahunMin', 'tahunMax'
        ));
    }

    private function getQueryLaporan($tahun, $bulan = null, $minggu = null, $komoditas_id = null)
    {
        $query = DB::table('master_komoditas')
            ->join('price_data', 'master_komoditas.id', '=', 'price_data.komoditas_id')
            ->leftJoin('price_forecasts', function ($join) {
                $join->on('master_komoditas.id', '=', 'price_forecasts.komoditas_id')
                     ->on('price_data.tanggal', '=', 'price_forecasts.tanggal');
            })
            ->select(
                'master_komoditas.id as id_komoditas',
                'master_komoditas.nama_komoditas',
                'master_komoditas.nama_varian',
                'price_data.tanggal',
                'price_data.harga as harga_aktual',
                'price_forecasts.harga_prediksi as harga_prediksi'
            )
            ->whereNotNull('price_data.tanggal')
            ->where('price_data.status', 'cleaned')
            ->where('price_data.is_outlier', false);

        if ($tahun) {
            $query->whereYear('price_data.tanggal', $tahun);
        }

        if ($bulan) {
            $query->whereMonth('price_data.tanggal', $bulan);
        }

        if ($minggu) {
            $query->whereRaw('FLOOR((DAY(price_data.tanggal) - 1) / 7) + 1 = ?', [$minggu]);
        }

        if ($komoditas_id) {
            $query->where('master_komoditas.id', $komoditas_id);
        }

        return $query->orderBy('price_data.tanggal', 'desc');
    }

    private function analisisDeskriptif($data)
    {
        $naik = 0; $turun = 0; $stabil = 0;

        foreach ($data as $item) {
            $aktual   = (float) ($item->harga_aktual   ?? 0);
            $prediksi = (float) ($item->harga_prediksi ?? 0);

            if ($aktual > 0 && $prediksi > 0) {
                if ($prediksi > $aktual)     $naik++;
                elseif ($prediksi < $aktual) $turun++;
                else                         $stabil++;
            }
        }

        return [
            'naik'       => $naik,
            'turun'      => $turun,
            'stabil'     => $stabil,
            'kesimpulan' => $this->generateKesimpulan($naik, $turun, $stabil),
        ];
    }

    private function generateKesimpulan($naik, $turun, $stabil)
    {
        if ($naik == 0 && $turun == 0 && $stabil == 0) return "Data tidak tersedia untuk periode ini.";
        if ($naik > $turun)  return "Tren harga cenderung mengalami kenaikan pada periode ini.";
        if ($turun > $naik)  return "Tren harga cenderung mengalami penurunan pada periode ini.";
        return "Harga cenderung stabil pada periode ini.";
    }

    public function cetak(Request $request)
    {
        $tahunMax     = PriceData::selectRaw('YEAR(MAX(tanggal)) as tahun_max')->value('tahun_max') ?? date('Y');
        $tahun        = $request->tahun ?? $tahunMax;
        $bulan        = $request->bulan;
        $minggu       = $request->minggu;
        $komoditas_id = $request->komoditas_id;

        $data     = $this->getQueryLaporan($tahun, $bulan, $minggu, $komoditas_id)->get();
        $analisis = $this->analisisDeskriptif($data);

        return view('laporan.cetak', compact('data', 'analisis'));
    }
}