<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PriceDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Bersihkan price_data ─────────────────────────────────
        $this->command->info('Membersihkan price_data...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('price_data')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ── Ambil ID komoditas yang valid (1-12) ─────────────────
        $validIds = DB::table('master_komoditas')
            ->pluck('id')
            ->toArray();

        $this->command->info('Komoditas valid: ' . implode(', ', $validIds));

        // ── Cari file CSV ────────────────────────────────────────
        $csvPath = database_path('seeders/csv/data_forecasting_transformed.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("File CSV tidak ditemukan di: {$csvPath}");
            $this->command->info("Letakkan file CSV di: database/seeders/csv/data_forecasting_transformed.csv");
            return;
        }

        $this->command->info("File CSV ditemukan. Membaca data...");

        $handle   = fopen($csvPath, 'r');
        $header   = array_map('trim', fgetcsv($handle));

        $this->command->info('Header: ' . implode(', ', $header));

        // Pastikan kolom yang dibutuhkan ada
        $required = ['id', 'tanggal', 'nama_komoditas', 'harga'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                $this->command->error("Kolom '{$col}' tidak ditemukan di CSV!");
                fclose($handle);
                return;
            }
        }

        $rows     = [];
        $inserted = 0;
        $skipped  = 0;

        while (($raw = fgetcsv($handle)) !== false) {
            if (count($raw) < count($header)) {
                $skipped++;
                continue;
            }

            $row = array_combine($header, array_map('trim', $raw));

            $komoditasId = (int) $row['id'];
            $tanggal     = trim($row['tanggal'], '"\'');
            $harga       = (float) str_replace([',', '"', "'"], '', $row['harga']);

            // Validasi
            if ($komoditasId <= 0 || $harga <= 0 || $tanggal === '') {
                $skipped++;
                continue;
            }

            // Pastikan komoditas_id ada di master_komoditas
            if (!in_array($komoditasId, $validIds)) {
                $skipped++;
                continue;
            }

            // Parse tanggal
            try {
                $tanggalParsed = Carbon::parse($tanggal)->format('Y-m-d');
            } catch (\Exception $e) {
                $skipped++;
                continue;
            }

            $rows[] = [
                'komoditas_id' => $komoditasId,
                'tanggal'      => $tanggalParsed,
                'harga'        => $harga,
                'created_at'   => now()->toDateTimeString(),
                'updated_at'   => now()->toDateTimeString(),
            ];

            $inserted++;

            // Batch insert per 500
            if (count($rows) >= 500) {
                DB::table('price_data')->insert($rows);
                $rows = [];
                $this->command->info("  Inserted {$inserted} baris...");
            }
        }

        // Insert sisa
        if (!empty($rows)) {
            DB::table('price_data')->insert($rows);
        }

        fclose($handle);

        $total = DB::table('price_data')->count();
        $this->command->info("\n✅ Selesai!");
        $this->command->info("   Inserted : {$inserted}");
        $this->command->info("   Skipped  : {$skipped}");
        $this->command->info("   Total DB : {$total}");

        // Ringkasan per komoditas
        $summary = DB::table('price_data as p')
            ->join('master_komoditas as k', 'k.id', '=', 'p.komoditas_id')
            ->selectRaw('p.komoditas_id, k.nama_komoditas, count(*) as total, min(p.tanggal) as dari, max(p.tanggal) as sampai')
            ->groupBy('p.komoditas_id', 'k.nama_komoditas')
            ->orderBy('p.komoditas_id')
            ->get();

        $this->command->info("\nRingkasan per komoditas:");
        foreach ($summary as $s) {
            $this->command->info("  ID {$s->komoditas_id} {$s->nama_komoditas}: {$s->total} baris ({$s->dari} s/d {$s->sampai})");
        }
    }
}