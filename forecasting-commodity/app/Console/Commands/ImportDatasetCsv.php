<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MasterKomoditas;
use App\Models\PriceData;
use Illuminate\Support\Facades\DB;

class ImportDatasetCsv extends Command
{
    protected $signature   = 'dataset:import {file : Path ke file CSV}';
    protected $description = 'Import dataset CSV ke tabel master_komoditas dan price_data';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");
            return 1;
        }

        $this->info("Membaca file: {$filePath}");

        $rows    = array_map('str_getcsv', file($filePath));
        $header  = array_shift($rows); // hapus baris header

        // Normalkan nama kolom (trim + lowercase)
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $this->info("Kolom ditemukan: " . implode(', ', $header));
        $this->info("Total baris data: " . count($rows));

        $inserted    = 0;
        $skipped     = 0;
        $komoditasMap = []; // cache agar tidak query berulang

        DB::beginTransaction();

        try {
            $bar = $this->output->createProgressBar(count($rows));
            $bar->start();

            foreach ($rows as $row) {
                // Map kolom ke nilai
                $data = array_combine($header, array_map('trim', $row));

                $namaKomoditas = $data['nama_komoditas'] ?? null;
                $tanggal       = $data['tanggal']        ?? null;
                $harga         = $data['harga']          ?? null;

                // Validasi dasar
                if (!$namaKomoditas || !$tanggal || !is_numeric($harga)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Get or create master_komoditas (pakai cache)
                $cacheKey = strtolower($namaKomoditas);
                if (!isset($komoditasMap[$cacheKey])) {
                    $komoditas = MasterKomoditas::firstOrCreate(
                        ['nama_komoditas' => $namaKomoditas, 'nama_varian' => null],
                        ['satuan' => 'Kg', 'kuantitas' => 1]
                    );
                    $komoditasMap[$cacheKey] = $komoditas->id;
                }

                $komoditasId = $komoditasMap[$cacheKey];

                // Insert price_data — skip kalau sudah ada (komoditas + tanggal sama)
                $exists = PriceData::where('komoditas_id', $komoditasId)
                    ->where('tanggal', $tanggal)
                    ->exists();

                if (!$exists) {
                    PriceData::create([
                        'komoditas_id' => $komoditasId,
                        'tanggal'      => $tanggal,
                        'harga'        => (float) $harga,
                        'status'       => 'cleaned', // langsung cleaned karena dari dataset resmi
                        'is_outlier'   => false,
                    ]);
                    $inserted++;
                } else {
                    $skipped++;
                }

                $bar->advance();
            }

            $bar->finish();
            DB::commit();

            $this->newLine();
            $this->info("✅ Selesai! Inserted: {$inserted} | Skipped: {$skipped}");
            $this->info("Total komoditas unik: " . count($komoditasMap));

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Gagal: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}