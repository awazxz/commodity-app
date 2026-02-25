<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterKomoditas;
use App\Models\PriceData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TestingDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Format CSV: id,tanggal,nama_komoditas,harga
     */
    public function run()
    {
        $csvPath = database_path('seeders/csv/testing_data.csv');
        
        if (!file_exists($csvPath)) {
            $this->command->error("File CSV tidak ditemukan: {$csvPath}");
            return;
        }

        $this->command->info("Importing testing_data.csv...");

        $csvData = array_map('str_getcsv', file($csvPath));
        
        // Remove header (id,tanggal,nama_komoditas,harga)
        $header = array_shift($csvData);
        
        $inserted = 0;
        $skipped = 0;
        $commodities = [];

        DB::beginTransaction();

        try {
            foreach ($csvData as $index => $row) {
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Expected format: id, tanggal, nama_komoditas, harga
                if (count($row) < 4) {
                    $this->command->warn("Row " . ($index + 2) . " skipped: incomplete data");
                    $skipped++;
                    continue;
                }

                $id = trim($row[0]);
                $tanggal = trim($row[1]);
                $namaKomoditas = trim($row[2]);
                $harga = trim($row[3]);

                // Validate date
                try {
                    $tanggalParsed = Carbon::parse($tanggal)->format('Y-m-d');
                } catch (\Exception $e) {
                    $this->command->warn("Row " . ($index + 2) . " skipped: invalid date '{$tanggal}'");
                    $skipped++;
                    continue;
                }

                // Validate price
                if (!is_numeric($harga) || $harga < 0) {
                    $this->command->warn("Row " . ($index + 2) . " skipped: invalid price '{$harga}'");
                    $skipped++;
                    continue;
                }

                // Get or create commodity (without varian)
                if (!isset($commodities[$namaKomoditas])) {
                    $komoditas = MasterKomoditas::firstOrCreate(
                        [
                            'nama_komoditas' => $namaKomoditas,
                            'nama_varian' => null
                        ],
                        [
                            'satuan' => 'Kg',
                            'kuantitas' => 1
                        ]
                    );
                    $commodities[$namaKomoditas] = $komoditas->id;
                    $this->command->info("  Created commodity: {$namaKomoditas}");
                }

                $komoditasId = $commodities[$namaKomoditas];

                // Insert price data
                try {
                    PriceData::create([
                        'komoditas_id' => $komoditasId,
                        'tanggal' => $tanggalParsed,
                        'harga' => $harga,
                        'status' => 'raw'
                    ]);
                    $inserted++;
                    
                    if ($inserted % 100 == 0) {
                        $this->command->info("  Inserted: {$inserted} records...");
                    }
                } catch (\Exception $e) {
                    // Duplicate entry, skip
                    $skipped++;
                    continue;
                }
            }

            DB::commit();

            $this->command->info("");
            $this->command->info("✅ Import completed!");
            $this->command->info("   Commodities: " . count($commodities));
            $this->command->info("   Inserted: {$inserted} price records");
            $this->command->info("   Skipped: {$skipped} records");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Import failed: " . $e->getMessage());
            $this->command->error($e->getTraceAsString());
        }
    }
}