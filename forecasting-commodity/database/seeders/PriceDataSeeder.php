<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterKomoditas;
use App\Models\PriceData;
use Carbon\Carbon;

class PriceDataSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders\csv\testing_data.csv');

        if (!file_exists($path)) {
            $this->command->error('File CSV tidak ditemukan');
            return;
        }

        $handle = fopen($path, 'r');

        // Ambil header CSV & bersihkan spasi
        $header = fgetcsv($handle, 0, ',');
        $header = array_map(fn ($h) => trim($h), $header);

        while (($row = fgetcsv($handle, 0, ',')) !== false) {

            // Pastikan kolom lengkap
            if (count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);

            // ===============================
            // MAPPING SESUAI DATASET KAMU
            // ===============================
            $namaVarian = trim($data['Patokan Varian']);
            $kuantitas  = trim($data['Kuantitas']);
            $satuan     = trim($data['Satuan']);
            $hargaRaw   = trim($data['Harga']);
            $waktuFull  = trim($data['Waktu_Full']); // 2020_Januari_M1

            // Validasi minimal
            if ($namaVarian === '' || $hargaRaw === '') {
                continue;
            }

            // Parse harga (aman untuk 14625.0)
            $harga = (float) str_replace(',', '', $hargaRaw);

            // Parse waktu: 2020_Januari_M1
            try {
                [$tahun, $bulan] = explode('_', $waktuFull);

                $tanggal = Carbon::createFromDate(
                    (int) $tahun,
                    $this->convertBulan($bulan),
                    1
                );
            } catch (\Throwable $e) {
                continue;
            }

            // ===============================
            // MASTER KOMODITAS (DIMENSI)
            // ===============================
            $komoditas = MasterKomoditas::firstOrCreate(
                [
                    'nama_komoditas' => $namaVarian,
                    'satuan' => $satuan,
                    'kuantitas' => $kuantitas,
                ],
                [
                    'nama_varian' => $namaVarian,
                ]
            );

            // ===============================
            // PRICE DATA (TIME SERIES)
            // ===============================
            PriceData::create([
                'komoditas_id' => $komoditas->id,
                'tanggal' => $tanggal,
                'harga' => $harga,
            ]);
        }

        fclose($handle);
    }

    private function convertBulan(string $bulan): int
    {
        return match (strtolower($bulan)) {
            'januari'   => 1,
            'februari'  => 2,
            'maret'     => 3,
            'april'     => 4,
            'mei'       => 5,
            'juni'      => 6,
            'juli'      => 7,
            'agustus'   => 8,
            'september' => 9,
            'oktober'   => 10,
            'november'  => 11,
            'desember'  => 12,
            default     => 1,
        };
    }
}
