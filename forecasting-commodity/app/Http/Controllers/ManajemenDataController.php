<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MasterKomoditas;
use App\Models\PriceData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ManajemenDataController extends Controller
{
    public function index()
    {
        $commodities = MasterKomoditas::orderBy('nama_komoditas')->get();
       

        $latestData = PriceData::with('komoditas')
            ->where('status', 'cleaned')
            ->orderBy('tanggal', 'desc')
            ->limit(20)
            ->get();

        $rawData = PriceData::with('komoditas')
            ->where('status', 'raw')
            ->orderBy('tanggal', 'desc')
            ->limit(20)
            ->get();

        $stats = [
            'total_commodities' => MasterKomoditas::count(),
            'total_data'        => PriceData::where('status', 'cleaned')->count(),
            'pending_clean'     => PriceData::where('status', 'raw')->count(),
            'outliers'          => PriceData::where('is_outlier', true)->count(),
        ];

        return view('admin.manajemen-data', compact(
            'commodities', 'latestData', 'rawData', 'stats'
        ));
    }

    public function storeManual(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'komoditas_id' => 'required|exists:master_komoditas,id',
            'tanggal'      => 'required|date',
            'harga'        => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Cek duplikat
            $exists = PriceData::where('komoditas_id', $request->komoditas_id)
                ->where('tanggal', $request->tanggal)
                ->exists();

            if ($exists) {
                return back()->with('error', 'Data untuk komoditas dan tanggal ini sudah ada.');
            }

            PriceData::create([
                'komoditas_id' => $request->komoditas_id,
                'tanggal'      => $request->tanggal,
                'harga'        => $request->harga,
                'status'       => 'cleaned',
                'is_outlier'   => false,
            ]);

            return back()->with('success', 'Data berhasil ditambahkan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    /**
     * Upload CSV file - Support 2 formats
     * Format 1: nama_komoditas,nama_varian,tanggal,harga
     * Format 2: id,tanggal,nama_komoditas,harga (testing_data.csv)
     */
    public function uploadCsv(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $rows   = array_map('str_getcsv', file($request->file('csv_file')->getRealPath()));
            $header = array_map(fn($h) => strtolower(trim($h)), array_shift($rows));

            // Detect format
            $format = 'unknown';
            if (in_array('nama_varian', $header)) {
                $format = 'format1'; // nama_komoditas,nama_varian,tanggal,harga
            } elseif (in_array('id', $header) && in_array('nama_komoditas', $header)) {
                $format = 'format2'; // id,tanggal,nama_komoditas,harga (testing_data.csv)
            }

            if ($format == 'unknown') {
                return back()->with('error', 'Format CSV tidak dikenali. Header: ' . implode(',', $header));
            }

            $inserted = 0;
            $skipped  = 0;
            $komoditasMap = [];

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                if (count($row) < 3) {
                    $skipped++;
                    continue;
                }

                // Parse based on format
                $namaKomoditas = null;
                $namaVarian = null;
                $tanggal = null;
                $harga = null;

                if ($format == 'format1') {
                    // Format: nama_komoditas, nama_varian, tanggal, harga
                    $data = array_combine($header, array_map('trim', $row));
                    
                    $namaKomoditas = $data['nama_komoditas'] ?? null;
                    $namaVarian    = $data['nama_varian']    ?? null;
                    $tanggal       = $data['tanggal']        ?? null;
                    $harga         = $data['harga']          ?? null;
                    
                } else {
                    // Format 2: id, tanggal, nama_komoditas, harga
                    $data = array_combine($header, array_map('trim', $row));
                    
                    $namaKomoditas = $data['nama_komoditas'] ?? null;
                    $namaVarian    = null; // No varian in testing_data.csv
                    $tanggal       = $data['tanggal']        ?? null;
                    $harga         = $data['harga']          ?? null;
                }

                // Validate
                if (!$namaKomoditas || !$tanggal || !is_numeric($harga)) {
                    $skipped++;
                    continue;
                }

                // Parse date
                try {
                    $tanggalParsed = Carbon::parse($tanggal)->format('Y-m-d');
                } catch (\Exception $e) {
                    $skipped++;
                    continue;
                }

                // Validate price
                if ($harga < 0) {
                    $skipped++;
                    continue;
                }

                // Get or create commodity (with cache)
                $cacheKey = strtolower($namaKomoditas . '|' . ($namaVarian ?: 'null'));
                if (!isset($komoditasMap[$cacheKey])) {
                    $kom = MasterKomoditas::firstOrCreate(
                        [
                            'nama_komoditas' => $namaKomoditas,
                            'nama_varian' => $namaVarian ?: null
                        ],
                        [
                            'satuan' => 'Kg',
                            'kuantitas' => 1
                        ]
                    );
                    $komoditasMap[$cacheKey] = $kom->id;
                }

                // Check duplicate
                $exists = PriceData::where('komoditas_id', $komoditasMap[$cacheKey])
                    ->where('tanggal', $tanggalParsed)
                    ->exists();

                if (!$exists) {
                    PriceData::create([
                        'komoditas_id' => $komoditasMap[$cacheKey],
                        'tanggal'      => $tanggalParsed,
                        'harga'        => (float) $harga,
                        'status'       => 'cleaned',
                        'is_outlier'   => false,
                    ]);
                    $inserted++;
                } else {
                    $skipped++;
                }

                // Progress indicator for large files
                if ($inserted % 500 == 0 && $inserted > 0) {
                    Log::info("CSV Upload Progress: {$inserted} records inserted...");
                }
            }

            DB::commit();

            $message = "✅ Berhasil upload {$inserted} data";
            if ($skipped > 0) {
                $message .= ". {$skipped} baris dilewati (duplikat/invalid).";
            }
            $message .= " Format: " . ($format == 'format1' ? 'Standard' : 'Testing Data');

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CSV Upload Error: ' . $e->getMessage());
            return back()->with('error', 'Gagal upload CSV: ' . $e->getMessage());
        }
    }

    public function detectOutliers(Request $request)
    {
        $request->validate(['komoditas_id' => 'required|exists:master_komoditas,id']);

        try {
            $data = PriceData::where('komoditas_id', $request->komoditas_id)
                ->where('is_outlier', false)
                ->orderBy('harga')
                ->pluck('harga')
                ->toArray();

            if (count($data) < 4) {
                return back()->with('info', 'Data terlalu sedikit untuk deteksi outlier (minimum 4).');
            }

            $count = count($data);
            $q1    = $data[(int) floor($count * 0.25)];
            $q3    = $data[(int) floor($count * 0.75)];
            $iqr   = $q3 - $q1;
            $lower = $q1 - 1.5 * $iqr;
            $upper = $q3 + 1.5 * $iqr;

            $found = PriceData::where('komoditas_id', $request->komoditas_id)
                ->where(fn($q) => $q->where('harga', '<', $lower)->orWhere('harga', '>', $upper))
                ->update(['is_outlier' => true]);

            return back()->with('success', "Ditemukan {$found} outlier. Q1={$q1}, Q3={$q3}, IQR={$iqr}.");

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal deteksi outlier: ' . $e->getMessage());
        }
    }

    public function deleteOutliers(Request $request)
    {
        $request->validate(['komoditas_id' => 'required|exists:master_komoditas,id']);

        $deleted = PriceData::where('komoditas_id', $request->komoditas_id)
            ->where('is_outlier', true)->delete();

        return back()->with('success', "Berhasil menghapus {$deleted} outlier.");
    }

    public function fillMissingValues(Request $request)
    {
        $request->validate(['komoditas_id' => 'required|exists:master_komoditas,id']);

        try {
            $komoditasId = $request->komoditas_id;
            $minDate = PriceData::where('komoditas_id', $komoditasId)->min('tanggal');
            $maxDate = PriceData::where('komoditas_id', $komoditasId)->max('tanggal');

            if (!$minDate) return back()->with('info', 'Tidak ada data untuk diproses.');

            $avgPrice = PriceData::where('komoditas_id', $komoditasId)
                ->where('is_outlier', false)->avg('harga');

            $start  = Carbon::parse($minDate);
            $end    = Carbon::parse($maxDate);
            $filled = 0;

            // Ambil semua tanggal yang sudah ada sekaligus (lebih efisien)
            $existingDates = PriceData::where('komoditas_id', $komoditasId)
                ->pluck('tanggal')->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
                ->toArray();

            while ($start->lte($end)) {
                $dateStr = $start->format('Y-m-d');
                if (!in_array($dateStr, $existingDates)) {
                    PriceData::create([
                        'komoditas_id' => $komoditasId,
                        'tanggal'      => $dateStr,
                        'harga'        => $avgPrice,
                        'status'       => 'cleaned',
                        'is_outlier'   => false,
                    ]);
                    $filled++;
                }
                $start->addDay();
            }

            return back()->with('success', "Berhasil mengisi {$filled} nilai hilang (avg: Rp " . number_format($avgPrice, 0, ',', '.') . ").");

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal isi nilai hilang: ' . $e->getMessage());
        }
    }

    public function markAsCleaned(Request $request)
    {
        $request->validate(['komoditas_id' => 'required|exists:master_komoditas,id']);

        $updated = PriceData::where('komoditas_id', $request->komoditas_id)
            ->where('status', 'raw')
            ->where('is_outlier', false)
            ->update(['status' => 'cleaned']);

        return back()->with('success', "Berhasil memvalidasi {$updated} data sebagai data bersih.");
    }

    public function deleteData($id)
    {
        try {
            PriceData::findOrFail($id)->delete();
            return back()->with('success', 'Data berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $csv = "nama_komoditas,nama_varian,tanggal,harga\n"
             . "Beras,Premium,2024-01-01,14500\n"
             . "Gula,Pasir,2024-01-01,12000\n"
             . "Minyak,Goreng,2024-01-01,15000\n"
             . "Cabai,Merah,2024-01-02,35000\n"
             . "Telur,Ayam Negeri,2024-01-02,28000\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_data_komoditas.csv"',
        ]);
    }
}