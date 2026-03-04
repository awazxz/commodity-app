<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterKomoditas;
use App\Models\PriceData;

class PriceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Baru (sesuai lokasi file di screenshot)
        $path = database_path('seeders/data_forecasting_transformed.csv');

        if (!file_exists($path)) {
            $this->command->error('File CSV tidak ditemukan: ' . $path);
            return;
        }

        $handle = fopen($path, 'r');

        // Ambil & bersihkan header
        $header = array_map(fn($h) => trim($h), fgetcsv($handle, 0, ','));

        // Cache komoditas agar tidak query DB berulang
        $komoditasCache = MasterKomoditas::pluck('id', 'nama_komoditas')->toArray();

        $inserted = 0;
        $skipped  = 0;
        $batch    = [];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) !== count($header)) {
                $skipped++;
                continue;
            }

            $data = array_combine($header, $row);

            $namaKomoditas = trim($data['nama_komoditas']);
            $tanggal       = trim($data['tanggal']);
            $harga         = (float) str_replace(',', '', trim($data['harga']));

            if ($namaKomoditas === '' || $tanggal === '' || $harga <= 0) {
                $skipped++;
                continue;
            }

            // Ambil komoditas_id dari cache, skip jika tidak ada di master
            if (!isset($komoditasCache[$namaKomoditas])) {
                $this->command->warn("Komoditas tidak ditemukan di master: {$namaKomoditas}");
                $skipped++;
                continue;
            }

            $batch[] = [
                'komoditas_id' => $komoditasCache[$namaKomoditas],
                'tanggal'      => $tanggal,
                'harga'        => $harga,
                'status'       => 'cleaned',
                'is_outlier'   => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            // Insert per 500 rows agar tidak memory leak
            if (count($batch) >= 500) {
                PriceData::insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        // Insert sisa batch
        if (!empty($batch)) {
            PriceData::insert($batch);
            $inserted += count($batch);
        }

        fclose($handle);

        $this->command->info("PriceDataSeeder selesai: {$inserted} rows inserted, {$skipped} rows skipped.");
    }
}