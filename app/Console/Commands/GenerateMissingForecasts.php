<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * php artisan forecast:generate-missing
 *
 * Men-trigger Flask endpoint /api/forecast/predict-advanced untuk setiap
 * komoditas yang belum punya data di price_forecasts pada bulan target.
 *
 * Opsi:
 *   --month=5      Bulan target (default: bulan sekarang)
 *   --year=2026    Tahun target (default: tahun sekarang)
 *   --all          Generate ulang SEMUA komoditas, bukan hanya yang kurang
 *   --ids=2,3,4    Generate hanya komoditas ID tertentu (comma-separated)
 *   --dry-run      Tampilkan komoditas yang akan di-generate tanpa eksekusi
 */
class GenerateMissingForecasts extends Command
{
    protected $signature = 'forecast:generate-missing
                            {--month=    : Bulan target (1-12, default bulan ini)}
                            {--year=     : Tahun target (default tahun ini)}
                            {--all       : Generate ulang semua komoditas}
                            {--ids=      : Generate hanya ID tertentu, pisahkan dengan koma}
                            {--dry-run   : Tampilkan saja tanpa eksekusi}
                            {--force     : Force retrain model meski sudah ada cache}';

    protected $description = 'Generate forecast Flask untuk komoditas yang belum punya data forecast';

    // URL Flask — sesuaikan jika berbeda
    private string $flaskBase = 'http://127.0.0.1:5000';

    // Hyperparameter default (sinkron dengan _DEFAULT_HP di app.py v8.2)
    private array $defaultHp = [
        'periods'                  => 12,   // 12 bulan ke depan
        'frequency'                => 'MS', // Monthly Start
        'changepoint_prior_scale'  => 0.1,
        'seasonality_prior_scale'  => 10.0,
        'seasonality_mode'         => 'additive',
        'weekly_seasonality'       => false,
        'yearly_seasonality'       => true,
        'yearly_fourier_order'     => 20,
        'monthly_seasonality'      => true,
        'n_changepoints'           => 25,
        'changepoint_range'        => 0.85,
        'force_retrain'            => false,
    ];

    public function handle(): int
    {
        $month    = (int) ($this->option('month')  ?: date('n'));
        $year     = (int) ($this->option('year')   ?: date('Y'));
        $forceAll = $this->option('all');
        $dryRun   = $this->option('dry-run');
        $force    = $this->option('force');
        $idsOpt   = $this->option('ids');

        $this->info("═══════════════════════════════════════════════");
        $this->info("  Generate Missing Forecasts");
        $this->info("  Target : {$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT));
        $this->info("  Mode   : " . ($forceAll ? 'SEMUA komoditas' : 'hanya yang kurang'));
        $this->info("  Dry run: " . ($dryRun ? 'YA' : 'tidak'));
        $this->info("═══════════════════════════════════════════════");

        // ── 1. Cek Flask hidup ─────────────────────────────────────
        $this->line("\n[1/4] Cek koneksi Flask...");
        try {
            $ping = Http::timeout(5)->get("{$this->flaskBase}/api/health");
            if (!$ping->successful()) {
                $this->error("Flask tidak merespon! Pastikan Flask berjalan di {$this->flaskBase}");
                return Command::FAILURE;
            }
            $this->info("    ✓ Flask online");
        } catch (\Exception $e) {
            $this->error("Gagal koneksi ke Flask: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // ── 2. Tentukan komoditas target ───────────────────────────
        $this->line("\n[2/4] Menentukan komoditas target...");

        if ($idsOpt) {
            // Mode: ID tertentu
            $targetIds = collect(explode(',', $idsOpt))
                ->map(fn($id) => (int) trim($id))
                ->filter()
                ->values();
            $this->line("    Mode ID manual: " . $targetIds->implode(', '));
        } elseif ($forceAll) {
            // Mode: semua komoditas
            $targetIds = DB::table('master_komoditas')->pluck('id');
            $this->line("    Mode semua: {$targetIds->count()} komoditas");
        } else {
            // Mode: hanya yang kurang (tidak ada di price_forecasts bulan target)
            $sudahAdaIds = DB::table('price_forecasts')
                ->whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->distinct()
                ->pluck('komoditas_id');

            $targetIds = DB::table('master_komoditas')
                ->whereNotIn('id', $sudahAdaIds)
                ->pluck('id');

            $this->line("    Sudah ada forecast : {$sudahAdaIds->count()} komoditas");
            $this->line("    Perlu di-generate  : {$targetIds->count()} komoditas");
        }

        if ($targetIds->isEmpty()) {
            $this->info("\n✓ Semua komoditas sudah punya forecast untuk {$year}-{$month}. Tidak ada yang perlu di-generate.");
            return Command::SUCCESS;
        }

        // ── 3. Tampilkan daftar target ─────────────────────────────
        $this->line("\n[3/4] Daftar komoditas yang akan di-generate:");

        $detail = DB::table('master_komoditas')
            ->whereIn('id', $targetIds->all())
            ->select('id', 'nama_komoditas', 'nama_varian')
            ->orderBy('id')
            ->get();

        $headers = ['ID', 'Nama Komoditas', 'Varian', 'Data Historis'];
        $rows    = [];

        foreach ($detail as $k) {
            $jumlah = DB::table('price_data')
                ->where('komoditas_id', $k->id)
                ->where('harga', '>', 0)
                ->count();
            $rows[] = [$k->id, $k->nama_komoditas, $k->nama_varian ?? '-', $jumlah];
        }

        $this->table($headers, $rows);

        if ($dryRun) {
            $this->warn("\n[DRY RUN] Tidak ada yang dieksekusi. Hapus --dry-run untuk mulai generate.");
            return Command::SUCCESS;
        }

        if (!$this->confirm("\nLanjutkan generate forecast untuk {$targetIds->count()} komoditas?", true)) {
            $this->line("Dibatalkan.");
            return Command::SUCCESS;
        }

        // ── 4. Generate forecast satu per satu ────────────────────
        $this->line("\n[4/4] Mulai generate forecast...\n");

        $hp = $this->defaultHp;
        if ($force) {
            $hp['force_retrain'] = true;
            $this->warn("    ⚠ force_retrain=true: semua model akan dilatih ulang");
        }

        $berhasil = 0;
        $gagal    = 0;
        $errors   = [];

        $bar = $this->output->createProgressBar($targetIds->count());
        $bar->start();

        foreach ($detail as $k) {
            $bar->advance();

            $payload = array_merge($hp, ['commodity_id' => $k->id]);

            try {
                $response = Http::timeout(600)// Prophet butuh waktu
                    ->post("{$this->flaskBase}/api/forecast/predict-advanced", $payload);

                if ($response->successful() && $response->json('success')) {
                    $berhasil++;
                    $savedToDb = $response->json('data.saved_to_db') ? '✓ DB' : '? DB';
                    $mape      = number_format($response->json('data.model_metrics.mape', 0), 2);
                    $this->line("\n    ✓ ID {$k->id} {$k->nama_komoditas} — MAPE: {$mape}% {$savedToDb}");
                } else {
                    $gagal++;
                    $msg = $response->json('message') ?? $response->body();
                    $errors[] = "ID {$k->id} {$k->nama_komoditas}: {$msg}";
                    $this->line("\n    ✗ ID {$k->id} {$k->nama_komoditas}: {$msg}");
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "ID {$k->id} {$k->nama_komoditas}: {$e->getMessage()}";
                $this->line("\n    ✗ ID {$k->id} {$k->nama_komoditas}: {$e->getMessage()}");
            }

            // Jeda kecil agar Flask tidak overload
            usleep(500_000); // 0.5 detik
        }

        $bar->finish();
        $this->line("\n");

        // ── Ringkasan ──────────────────────────────────────────────
        $this->info("═══════════════════════════════════════════════");
        $this->info("  SELESAI");
        $this->info("  ✓ Berhasil : {$berhasil}");
        if ($gagal > 0) {
            $this->warn("  ✗ Gagal    : {$gagal}");
            foreach ($errors as $err) {
                $this->warn("    - {$err}");
            }
        }
        $this->info("═══════════════════════════════════════════════");

        // Verifikasi akhir
        $this->line("\nVerifikasi price_forecasts untuk {$year}-{$month}:");
        $finalCount = DB::table('price_forecasts')
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->distinct('komoditas_id')
            ->count('komoditas_id');
        $total = DB::table('master_komoditas')->count();
        $this->info("  {$finalCount} dari {$total} komoditas punya forecast.");

        return $gagal === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}