<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CommodityPrice;
use App\Models\MasterKomoditas;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ManajemenDataController extends Controller
{
    // =========================================================
    // INDEX — tampilkan halaman manajemen data
    // =========================================================
    public function index(Request $request)
    {
        $commodities = MasterKomoditas::orderBy('nama_komoditas')->get();

        $selectedKomoditasId = (int) ($request->query('komoditas_id') ?? optional($commodities->first())->id);

        $latestData = CommodityPrice::where('komoditas_id', $selectedKomoditasId)
            ->orderBy('tanggal', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('manajemen_data', compact('commodities', 'selectedKomoditasId', 'latestData'));
    }

    // =========================================================
    // STORE MANUAL — tambah satu data
    // =========================================================
    public function storeManual(Request $request)
    {
        $request->validate([
            'komoditas_id' => 'required|integer',
            'date'         => 'required|date',
            'price'        => 'required|numeric|min:1',
        ]);

        try {
            CommodityPrice::updateOrCreate(
                [
                    'komoditas_id' => $request->komoditas_id,
                    'tanggal'      => $request->date,
                ],
                [
                    'harga'      => $request->price,
                    'updated_at' => now(),
                ]
            );

            return redirect()->back()->with('success', 'Data berhasil ditambahkan!');
        } catch (\Exception $e) {
            Log::error('[MANAJEMEN DATA] storeManual error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    // =========================================================
    // UPLOAD CSV — support dua format:
    //   Format 1 (lama): komoditas_id, tanggal, harga
    //   Format 2 (baru): id, tanggal, nama_komoditas, harga
    // =========================================================
    public function uploadCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        try {
            $file   = $request->file('csv_file');
            $handle = fopen($file->getRealPath(), 'r');

            // Baca header untuk deteksi format kolom
            $header = fgetcsv($handle);
            $header = array_map('trim', array_map('strtolower', $header));

            // Deteksi posisi kolom secara dinamis
            $hasNamaKomoditas = in_array('nama_komoditas', $header);
            $idxKomoditas     = array_search('komoditas_id', $header) !== false
                                    ? array_search('komoditas_id', $header)
                                    : array_search('id', $header);
            $idxNama          = array_search('nama_komoditas', $header);
            $idxTanggal       = array_search('tanggal', $header);
            $idxHarga         = array_search('harga', $header);

            // Validasi header minimal
            if ($idxTanggal === false || $idxHarga === false) {
                fclose($handle);
                return redirect()->back()->with('error',
                    'Format CSV tidak valid. Pastikan ada kolom "tanggal" dan "harga".'
                );
            }

            // Cache semua komoditas (nama lowercase → id) untuk lookup cepat
            $komoditasMap = MasterKomoditas::all()
                ->mapWithKeys(fn($k) => [strtolower(trim($k->nama_komoditas)) => $k->id]);

            $insertedCount = 0;
            $errorCount    = 0;
            $errorDetails  = [];

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                // Skip baris kosong
                if (empty(array_filter($row))) continue;

                try {
                    // ── Resolve komoditas_id ──────────────────────
                    if ($hasNamaKomoditas && $idxNama !== false) {
                        $namaKomoditas = strtolower(trim($row[$idxNama]));
                        $komoditasId   = $komoditasMap->get($namaKomoditas);

                        if (!$komoditasId) {
                            Log::warning("[UPLOAD CSV] Nama komoditas tidak ditemukan: {$row[$idxNama]}");
                            $errorDetails[] = "Komoditas '{$row[$idxNama]}' tidak ditemukan di database";
                            $errorCount++;
                            continue;
                        }
                    } else {
                        $komoditasId = (int) trim($row[$idxKomoditas]);
                        if ($komoditasId <= 0) {
                            $errorDetails[] = "komoditas_id tidak valid: {$row[$idxKomoditas]}";
                            $errorCount++;
                            continue;
                        }
                    }

                    // ── Resolve tanggal ───────────────────────────
                    $tanggal = trim($row[$idxTanggal]);
                    try {
                        Carbon::parse($tanggal);
                    } catch (\Exception $e) {
                        $errorDetails[] = "Format tanggal tidak valid: {$tanggal}";
                        $errorCount++;
                        continue;
                    }

                    // ── Resolve harga ─────────────────────────────
                    $harga = (float) str_replace([',', ' '], ['', ''], trim($row[$idxHarga]));
                    if ($harga <= 0) {
                        $errorDetails[] = "Harga tidak valid pada tanggal {$tanggal}";
                        $errorCount++;
                        continue;
                    }

                    // ── Simpan ke database ────────────────────────
                    CommodityPrice::updateOrCreate(
                        [
                            'komoditas_id' => $komoditasId,
                            'tanggal'      => $tanggal,
                        ],
                        [
                            'harga'      => $harga,
                            'updated_at' => now(),
                        ]
                    );
                    $insertedCount++;

                } catch (\Exception $e) {
                    Log::warning('[UPLOAD CSV] Baris gagal: ' . implode(',', $row) . ' — ' . $e->getMessage());
                    $errorCount++;
                }
            }
            fclose($handle);

            // ── Susun pesan hasil ─────────────────────────────────
            $msg = "Bulk upload selesai! {$insertedCount} data berhasil diproses.";
            if ($errorCount > 0) {
                $uniqueErrors = array_unique($errorDetails);
                $errorSample  = implode('; ', array_slice($uniqueErrors, 0, 3));
                $msg .= " {$errorCount} baris gagal. Contoh: {$errorSample}.";
            }

            return redirect($this->resolveRedirectRoute($request))->with('success', $msg);

        } catch (\Exception $e) {
            Log::error('[MANAJEMEN DATA] uploadCsv error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal mengunggah CSV: ' . $e->getMessage());
        }
    }

    // =========================================================
    // DOWNLOAD TEMPLATE CSV
    // ?format=nama  → format baru  (id, tanggal, nama_komoditas, harga)
    // ?format=id    → format lama  (komoditas_id, tanggal, harga)
    // =========================================================
    public function downloadTemplate(Request $request)
    {
        $format = $request->query('format', 'nama');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_data_komoditas.csv"',
        ];

        if ($format === 'id') {
            $columns    = ['komoditas_id', 'tanggal', 'harga'];
            $sampleData = [
                ['9',  '2020-01-06', '6000'],
                ['10', '2020-01-06', '7000'],
                ['11', '2020-01-06', '25100'],
            ];
        } else {
            $columns    = ['id', 'tanggal', 'nama_komoditas', 'harga'];
            $sampleData = [
                ['1', '2020-01-06', 'Jagung',                         '6000'],
                ['2', '2020-01-06', 'Tepung Terigu (Protein Sedang)', '7000'],
                ['3', '2020-01-06', 'Kedelai',                        '25100'],
                ['4', '2020-01-06', 'Cabe Merah Keriting',            '37800'],
                ['5', '2020-01-06', 'Cabe Rawit Hijau',               '36000'],
                ['6', '2020-01-06', 'Bawang Merah',                   '32300'],
                ['7', '2020-01-06', 'Bawang Putih',                   '34000'],
                ['8', '2020-01-06', 'Ikan Kembung',                   '86500'],
            ];
        }

        $callback = function () use ($columns, $sampleData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // =========================================================
    // DETECT OUTLIERS — scan dan kembalikan info outlier
    // =========================================================
    public function detectOutliers(Request $request)
    {
        $request->validate(['komoditas_id' => 'required|integer']);

        $komoditasId = $request->input('komoditas_id');

        try {
            $prices = CommodityPrice::where('komoditas_id', $komoditasId)
                ->where('harga', '>', 0)
                ->pluck('harga')
                ->toArray();

            if (count($prices) < 4) {
                return redirect()->back()->with('info',
                    'Data tidak cukup untuk deteksi outlier (minimal 4 data).'
                );
            }

            sort($prices);
            $q1  = $prices[(int) floor(count($prices) * 0.25)];
            $q3  = $prices[(int) floor(count($prices) * 0.75)];
            $iqr = $q3 - $q1;

            $lowerBound = $q1 - 1.5 * $iqr;
            $upperBound = $q3 + 1.5 * $iqr;

            $outlierCount = CommodityPrice::where('komoditas_id', $komoditasId)
                ->where(function ($q) use ($lowerBound, $upperBound) {
                    $q->where('harga', '<', $lowerBound)
                      ->orWhere('harga', '>', $upperBound);
                })->count();

            $msg = $outlierCount > 0
                ? "Ditemukan {$outlierCount} outlier. Batas normal: Rp " .
                  number_format($lowerBound, 0, ',', '.') . " – Rp " .
                  number_format($upperBound, 0, ',', '.') . "."
                : "Tidak ditemukan outlier. Data sudah bersih.";

            return redirect()->back()->with('success', $msg);

        } catch (\Exception $e) {
            Log::error('[MANAJEMEN DATA] detectOutliers error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal mendeteksi outlier: ' . $e->getMessage());
        }
    }

    // =========================================================
    // DELETE OUTLIERS — hapus atau replace outlier dengan IQR
    // =========================================================
    public function deleteOutliers(Request $request)
    {
        $request->validate([
            'komoditas_id' => 'required|integer',
            'method'       => 'nullable|in:remove,mean,median',
        ]);

        $komoditasId = $request->input('komoditas_id');
        $method      = $request->input('method', 'remove');

        try {
            $prices = CommodityPrice::where('komoditas_id', $komoditasId)
                ->where('harga', '>', 0)
                ->pluck('harga')
                ->toArray();

            if (empty($prices)) {
                return redirect()->back()->with('error', 'Tidak ada data untuk diproses.');
            }

            sort($prices);
            $q1  = $prices[(int) floor(count($prices) * 0.25)];
            $q3  = $prices[(int) floor(count($prices) * 0.75)];
            $iqr = $q3 - $q1;

            $lowerBound = $q1 - 1.5 * $iqr;
            $upperBound = $q3 + 1.5 * $iqr;

            $outlierQuery = CommodityPrice::where('komoditas_id', $komoditasId)
                ->where(function ($q) use ($lowerBound, $upperBound) {
                    $q->where('harga', '<', $lowerBound)
                      ->orWhere('harga', '>', $upperBound);
                });

            $count = $outlierQuery->count();

            if ($count === 0) {
                return redirect()->back()->with('info', 'Tidak ada outlier yang ditemukan.');
            }

            if ($method === 'remove') {
                $outlierQuery->delete();
                return redirect()->back()->with('success', "{$count} outlier berhasil dihapus.");
            }

            $replacement = $method === 'median'
                ? $prices[(int) floor(count($prices) / 2)]
                : array_sum($prices) / count($prices);

            $outlierQuery->update([
                'harga'      => round($replacement, 2),
                'updated_at' => now(),
            ]);

            $label = $method === 'median' ? 'median' : 'rata-rata';
            return redirect()->back()->with('success',
                "{$count} outlier berhasil diganti dengan {$label} (Rp " .
                number_format($replacement, 0, ',', '.') . ")."
            );

        } catch (\Exception $e) {
            Log::error('[MANAJEMEN DATA] deleteOutliers error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal memproses outlier: ' . $e->getMessage());
        }
    }

    // =========================================================
    // FILL MISSING VALUES — isi nilai kosong/nol
    // =========================================================
    public function fillMissingValues(Request $request)
    {
        $request->validate([
            'komoditas_id' => 'required|integer',
            'method'       => 'nullable|in:mean,median,remove',
        ]);

        $komoditasId = $request->input('komoditas_id');
        $method      = $request->input('method', 'mean');

        try {
            $missingQuery = CommodityPrice::where('komoditas_id', $komoditasId)
                ->where(function ($q) {
                    $q->whereNull('harga')->orWhere('harga', '<=', 0);
                });

            $count = $missingQuery->count();

            if ($count === 0) {
                return redirect()->back()->with('info', 'Tidak ada nilai hilang yang ditemukan.');
            }

            if ($method === 'remove') {
                $missingQuery->delete();
                return redirect()->back()->with('success', "{$count} data kosong berhasil dihapus.");
            }

            // Ambil harga valid sebagai referensi
            $prices = CommodityPrice::where('komoditas_id', $komoditasId)
                ->where('harga', '>', 0)
                ->pluck('harga')
                ->toArray();

            if (empty($prices)) {
                return redirect()->back()->with('error',
                    'Tidak ada data valid sebagai referensi pengisian.'
                );
            }

            sort($prices);
            $replacement = $method === 'median'
                ? $prices[(int) floor(count($prices) / 2)]
                : array_sum($prices) / count($prices);

            $missingQuery->update([
                'harga'      => round($replacement, 2),
                'updated_at' => now(),
            ]);

            $label = $method === 'median' ? 'median' : 'rata-rata';
            return redirect()->back()->with('success',
                "{$count} nilai hilang berhasil diisi dengan {$label} (Rp " .
                number_format($replacement, 0, ',', '.') . ")."
            );

        } catch (\Exception $e) {
            Log::error('[MANAJEMEN DATA] fillMissingValues error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal mengisi nilai hilang: ' . $e->getMessage());
        }
    }

    // =========================================================
    // MARK AS CLEANED — tandai data sudah dibersihkan
    // =========================================================
    public function markAsCleaned(Request $request)
    {
        return redirect()->back()->with('success', 'Data ditandai sebagai sudah dibersihkan.');
    }

    // =========================================================
    // DELETE SINGLE DATA
    // =========================================================
    public function deleteData($id)
    {
        try {
            CommodityPrice::findOrFail($id)->delete();
            return redirect()->back()->with('success', 'Data berhasil dihapus!');
        } catch (\Exception $e) {
            Log::error('[MANAJEMEN DATA] deleteData error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus data.');
        }
    }

    // =========================================================
    // HELPER — resolve redirect URL berdasarkan referer/role
    // =========================================================
    private function resolveRedirectRoute(Request $request): string
    {
        $referer = $request->headers->get('referer', '');

        if (str_contains($referer, '/operator/')) {
            return route('operator.predict', ['tab' => 'manage']);
        }

        if (str_contains($referer, '/admin/')) {
            return route('admin.predict', ['tab' => 'manage']);
        }

        return $referer ?: url('/');
    }
}