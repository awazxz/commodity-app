<?php

return [
    // ── NAVBAR ──────────────────────────────────────────────────
    'beranda'                   => 'Home',
    'analisis'                  => 'Analysis',
    'manajemen_data'            => 'Data Management',
    'manajemen_pengguna'        => 'User Management',
    'logout'                    => 'Logout',
    'pilih_bahasa'              => 'Language',
    'tampilan'                  => 'Theme',
    'login_sebagai'             => 'Logged in as:',
    'horizon_prediksi'          => 'Prediction Horizon',

    // ── ROLE LABELS ──────────────────────────────────────────────
    'administrator'             => 'Administrator',
    'operator'                  => 'Operator',
    'pengguna'                  => 'User',

    // ── HEADER DASHBOARD ─────────────────────────────────────────
    'judul_sistem'              => 'Commodity Price Prediction Analysis System',
    'panel_admin'               => 'Administrator Panel — BPS Riau Province',
    'panel_operator'            => 'Operator Panel — BPS Riau Province',
    'panel_pengguna'            => 'User Panel — BPS Riau Province',

    // ── TAB NAVIGATION ───────────────────────────────────────────
    'tab_insight'               => 'Insight & Prediction',
    'tab_manajemen_data'        => 'Data Management',
    'tab_kelola_pengguna'       => 'Manage Users',
    'tab_laporan'               => 'Commodity Report',

    // ── FILTER & FORM ────────────────────────────────────────────
    'komoditas_terpilih'        => 'Selected Commodity',
    'rentang_waktu'             => 'Historical Analysis Time Range',
    'periode_prediksi'          => 'Prediction Period (Days)',
    'perbarui_prediksi'         => 'Update Prediction',

    // ── METRIC CARDS ─────────────────────────────────────────────
    'rata_rata_harga'           => 'Average Price',
    'harga_tertinggi'           => 'Highest Price',
    'harga_terendah'            => 'Lowest Price',
    'periode_data'              => 'Data Period',
    'arah_tren'                 => 'Trend Direction',
    'data_poin'                 => 'data points',
    'data_historis'             => 'Historical data',

    // ── CHART ────────────────────────────────────────────────────
    'visualisasi_tren'          => 'Trend & Projection Visualization',
    'data_historis_vs_proyeksi' => 'Historical Data vs Projection',
    'mingguan'                  => 'Weekly',
    'bulanan'                   => 'Monthly',
    'tahunan'                   => 'Yearly',
    'harga_aktual'              => 'Actual Price',
    'harga_prediksi'            => 'Predicted Price',
    'harga_proyeksi'            => 'Projected Price',
    'rentang_bawah'             => 'Lower Bound',
    'rentang_atas'              => 'Upper Bound',
    'tidak_ada_data'            => 'No data for this period',

    // ── INSIGHT TABLE ────────────────────────────────────────────
    'ringkasan_analisis'        => 'Analysis Summary',
    'periode'                   => 'Period',
    'selisih'                   => 'Difference',
    'indikator'                 => 'Indicator',
    'naik'                      => 'Up',
    'turun'                     => 'Down',
    'stabil'                    => 'Stable',
    'proyeksi'                  => 'Projection',

   // ── INTERPRETASI ─────────────────────────────────────────────
    'interpretasi_tren'         => 'Trend Analysis Interpretation',
    'berdasarkan_analisis'      => 'Based on historical data analysis for commodity',
    'model_deteksi'             => 'the model detects a price trend of',
    'rata_rata_harga_label'     => 'with an average price of',
    'total_label'               => 'and a total of',
    'pada_periode'              => 'for the period',

    // ── DETAIL MODEL & EVALUASI (TAMBAHAN) ──────────────────────
    'model_prophet_dilatih'     => 'The Prophet model is trained with',
    'horizon_prediksi_label'    => 'prediction horizon',
    'minggu_ke_depan'           => 'weeks ahead',
    'nilai_mape_label'          => 'MAPE value (80/20 Cross-Validation) of',
    'menunjukkan'               => 'indicates',
    'akurasi_sangat_baik'       => 'excellent accuracy',
    'akurasi_baik'              => 'good accuracy',
    'perlu_penyesuaian'         => 'hyperparameter adjustment needed',
    's_d'                       => 'to',

    // ── STATISTIK ────────────────────────────────────────────────
    'ringkasan_statistik'       => 'Statistical Summary',
    'mape'                      => 'MAPE (Variation)',
    'r_squared'                 => 'R-Squared Score',
    'total_data_poin'           => 'Total Data Points',

    // ── HYPERPARAMETER ───────────────────────────────────────────
    'pengaturan_hyperparameter' => 'Hyperparameter Settings',
    'changepoint_prior'         => 'Changepoint Prior',
    'seasonality_prior'         => 'Seasonality Prior',
    'mode_musiman'              => 'Seasonal Mode',
    'multiplikatif'             => 'Multiplicative',
    'aditif'                    => 'Additive',
    'komponen_musiman'          => 'Seasonal Components',
    'fleksibilitas_tren'        => 'Trend change flexibility',
    'kekuatan_musiman'          => 'Seasonal pattern strength',
    'metode_musiman'            => 'Seasonal application method',

    // ── MANAJEMEN DATA ───────────────────────────────────────────
    'tambah_data_baru'          => 'Add New Data',
    'manual'                    => 'Manual',
    'unggah_csv'                => 'Upload CSV',
    'komoditas'                 => 'Commodity',
    'tanggal'                   => 'Date',
    'harga'                     => 'Price (Rp)',
    'simpan_data'               => 'Save Data',
    'unggah_file_csv'           => 'Upload CSV File',
    'riwayat_database'          => 'Database History',
    'pilih_komoditas'           => '-- Select Commodity --',
    'masukkan_harga'            => 'Enter price',
    'template_csv'              => 'CSV Template',
    'unduh_template_csv'        => 'Download CSV Template',
    'unggah_dataset'            => 'Upload Dataset',
    'pilih_seret_csv'           => 'Select or drag CSV file here',
    'data_tidak_ditemukan'      => 'Data not found',
    'pilih_atau_tambah'         => 'Select a commodity or add new data',

    // ── PEMBERSIHAN DATA ─────────────────────────────────────────
    'pembersihan_data'          => 'Data Cleaning',
    'pindai_data'               => 'Scan Data For',
    'pindai'                    => 'Scan',
    'deteksi_outlier'           => 'Outlier Detection',
    'hapus_outlier'             => 'Remove Outliers',
    'ganti_rata_rata'           => 'Replace with Mean',
    'ganti_median'              => 'Replace with Median',
    'nilai_hilang'              => 'Missing Values',
    'isi_rata_rata'             => 'Fill with Mean',
    'isi_median'                => 'Fill with Median',
    'hapus_data_kosong'         => 'Remove Empty Data',
    'terapkan'                  => 'Apply',
    'hasil_pemindaian'          => 'Scan Results',
    'temuan'                    => 'Findings',
    'jenis_masalah'             => 'Issue Type',
    'nilai'                     => 'Value',
    'status'                    => 'Status',
    'tidak_ada_masalah'         => 'No issues detected',
    'data_sudah_bersih'         => 'Data is already clean',

    // ── AKSI TABEL ───────────────────────────────────────────────
    'edit'                      => 'Edit',
    'hapus'                     => 'Delete',
    'selesai'                   => 'Done',
    'aksi'                      => 'Action',
    'menampilkan'               => 'Showing',
    'dari'                      => 'of',
    'data'                      => 'records',

    // ── MANAJEMEN PENGGUNA ───────────────────────────────────────
    'ringkasan_pengguna'        => 'User Summary',
    'total_pengguna'            => 'Total Users',
    'aktif'                     => 'Active',
    'buat_pengguna_baru'        => 'Create New User',
    'nama_lengkap'              => 'Full Name',
    'masukkan_nama'             => 'Enter full name',
    'alamat_email'              => 'Email Address',
    'kata_sandi'                => 'Password',
    'min_8_karakter'            => '(min. 8 characters)',
    'minimal_8'                 => 'Minimum 8 characters',
    'role'                      => 'Role',
    'buat_pengguna'             => 'Create User',
    'kelola_akses'              => 'Manage User Access',
    'informasi_pengguna'        => 'User Information',
    'email'                     => 'Email',
    'peran'                     => 'Role',
    'akun_aktif'                => 'Active Account',

    // ── LAPORAN KOMODITAS ────────────────────────────────────────
    'laporan_harga'             => 'Commodity Price Report',
    'analisis_deskriptif'       => 'Descriptive comparison of actual vs predicted prices for weekly periods.',
    'cetak_laporan'             => 'Print Report',
    'ringkasan_analisis_desk'   => 'Descriptive Analysis Summary',
    'prediksi_naik'             => 'Predicted Up',
    'prediksi_turun'            => 'Predicted Down',
    'harga_stabil'              => 'Stable Price',
    'filter'                    => 'Filter',
    'reset'                     => 'Reset',
    'tahun'                     => 'Year',
    'bulan'                     => 'Month',
    'semua_komoditas'           => 'All Commodities',
    'semua_bulan'               => 'All Months',
    'semua_minggu'              => 'All Weeks',
    'minggu_ke'                 => 'Week ',
    'komoditas_varian'          => 'Commodity & Variant',
    'trend'                     => 'Trend',
    'belum_ada_prediksi'        => 'No prediction yet',
    'batas_atas'                => 'Upper bound',
    'batas_bawah'               => 'Lower bound',
    'aktual&prediksi'           => 'Actual + Prediction',
    'hanya_prediksi'            => 'Prediction Only',
    'hanya_aktual'              => 'Actual Only',

    // ── STATUS & NOTIFIKASI ──────────────────────────────────────
    'API_aktif'                 => 'API Active',
    'api_offline'               => 'API Offline',
    'prediksi_tersedia'         => '✓ Available',
    'prediksi_tidak_tersedia'   => '⚠ Unavailable',
    'model_offline'             => 'Prediction Model Offline',
    'server_python_offline'     => 'Python server (Prophet API) is currently inactive. Chart shows historical data only without projections.',

    // ── AKSES CEPAT ──────────────────────────────────────────────
    'akses_cepat'               => 'Quick Access',
    'lihat_data_cetak'          => 'View complete data & print report',
    'buka_cetak_pdf'            => 'Open PDF print page',

    // ── KOMODITAS TERSEDIA ───────────────────────────────────────
    'komoditas_tersedia'        => 'Available Commodities',
    'klik_lihat_analisis'       => 'Click to view analysis for other commodities',
    'tidak_ada_komoditas'       => 'No commodities available.',

    // ── TAMPILAN / THEME ─────────────────────────────────────────
    'mode_terang'               => 'Light Mode',
    'mode_gelap'                => 'Dark Mode',

    // ── NAMA BULAN ───────────────────────────────────────────────────────
'bulan_januari'     => 'January',
'bulan_februari'    => 'February',
'bulan_maret'       => 'March',
'bulan_april'       => 'April',
'bulan_mei'         => 'May',
'bulan_juni'        => 'June',
'bulan_juli'        => 'July',
'bulan_agustus'     => 'August',
'bulan_september'   => 'September',
'bulan_oktober'     => 'October',
'bulan_november'    => 'November',
'bulan_desember'    => 'December',

// ── KESIMPULAN ANALISIS (pakai :placeholder untuk angka dinamis) 
'kesimpulan_naik'   => 'Of :total commodities analyzed, most show a predicted price increase (:naik up, :turun down, :stabil stable). Price control measures may be needed.',
'kesimpulan_turun'  => 'Of :total commodities analyzed, most show a predicted price decrease (:naik up, :turun down, :stabil stable). Prices are expected to ease.',
'kesimpulan_stabil' => 'Of :total commodities analyzed, most prices are predicted to remain stable (:naik up, :turun down, :stabil stable). Market conditions are relatively under control.',
'kesimpulan_kosong' => 'No data available to analyze for this period.',

// ── HYPERPARAMETER NOTES (admin & operator dashboard)
'deteksi_pola_minggu'          => 'Detect weekly patterns',
'deteksi_pola_tahun'           => 'Detect yearly patterns',
'berubah_saat_hyperparameter'  => 'Changes when hyperparameters change',
'prediksi_terakhir_note'       => 'Last prediction used the parameters above.',
'ubah_parameter_note'          => 'Change parameters → click "Update Prediction" for new insights.',
'gunakan_template_standar'     => 'Use the standard template to ensure correct data format',
'format_csv_admin'             => 'Format: komoditas_id, date, price',
'format_csv_operator'          => 'Format: komoditas_id, date, price',

// ── FLASK API STATUS 
'memeriksa'         => 'Checking...',

// ── INTERPRETASI TREN (admin & operator dashboard) 
'interpretasi_tren'         => 'Trend Analysis Interpretation',
'berdasarkan_analisis'      => 'Based on historical data analysis for commodity',
'model_deteksi'             => 'the model detects a price trend of',
'rata_rata_harga_label'     => 'with an average price of',
'total_label'               => 'and a total of',
'pada_periode'              => 'for the period',
];