<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatasetController extends Controller
{
    public function downloadTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_dataset_komoditas.csv"',
        ];

        $columns = ['id', 'tanggal', 'nama_komoditas', 'harga'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Header CSV
            fputcsv($file, $columns);

            // Contoh data
            fputcsv($file, [1, '2025-01-01', 'Beras Premium', 13500]);
            fputcsv($file, [2, '2025-01-01', 'Cabai Merah', 42000]);
            fputcsv($file, [3, '2025-01-01', 'Minyak Goreng', 16000]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
