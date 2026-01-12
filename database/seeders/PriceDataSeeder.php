<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PriceData;
use Illuminate\Support\Facades\DB;

class PriceDataSeeder extends Seeder
{
    public function run()
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        // 1. DAFTAR LOKASI PENCARIAN FILE
        $locations = [
            base_path('newcommoditydataset.csv'), // Di Root
            database_path('seeders/csv/newcommoditydataset.csv'), // Di Database/Seeders/csv
            base_path('dataset/seeder/csv/newcommoditydataset.csv'), // Di folder dataset
        ];

        $filePath = null;
        foreach ($locations as $loc) {
            if (file_exists($loc)) {
                $filePath = $loc;
                break;
            }
        }

        if (!$filePath) {
            $this->command->error("File TIDAK ditemukan di lokasi manapun:");
            foreach ($locations as $loc) { $this->command->line(" - $loc"); }
            return;
        }

        $this->command->info("File ditemukan di: $filePath");

        // 2. PROSES MEMBERSIHKAN TABEL
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PriceData::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $file = fopen($filePath, 'r');
        fgetcsv($file); // Skip header

        $this->command->info("Sedang mengimpor data...");

        DB::beginTransaction();
        $count = 0;

        try {
            while (($line = fgets($file)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;

                // Membersihkan kutip ganda yang rusak
                if (str_starts_with($line, '"') && str_ends_with($line, '"')) {
                    $line = substr($line, 1, -1);
                }
                $line = str_replace('""', '"', $line);

                // Menggunakan str_getcsv untuk parsing yang aman
                $row = str_getcsv($line, ",");

                if (count($row) >= 9) {
                    // Reverse Mapping untuk keamanan kolom nama yang mengandung koma
                    $minggu    = end($row);
                    $bulan     = prev($row);
                    $tahun     = prev($row);
                    $harga     = prev($row);
                    $waktu     = prev($row);
                    $satuan    = prev($row);
                    $kuantitas  = prev($row);
                    
                    // Nama varian adalah sisa kolom di tengah
                    $nama_varian = implode(',', array_slice($row, 1, count($row) - 8));

                    PriceData::create([
                        'nama_varian' => trim($nama_varian, '" '),
                        'kuantitas'   => $kuantitas,
                        'satuan'      => $satuan,
                        'harga'       => (float)$harga,
                        'tahun'       => (int)$tahun,
                        'bulan'       => $bulan,
                        'minggu'      => $minggu,
                    ]);

                    $count++;
                    if ($count % 1000 === 0) {
                        $this->command->info("Telah mengimpor $count data...");
                    }
                }
            }

            DB::commit();
            $this->command->info("----------------------------------------");
            $this->command->info("BERHASIL! Total $count data masuk ke database.");
            $this->command->info("----------------------------------------");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Error: " . $e->getMessage());
        }

        fclose($file);
    }
}