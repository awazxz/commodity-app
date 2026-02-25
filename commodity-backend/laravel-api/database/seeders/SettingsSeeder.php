<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'setting_key' => 'forecast_period',
                'setting_value' => '12',
                'setting_type' => 'integer',
                'description' => 'Jumlah minggu prediksi ke depan (default)',
            ],
            [
                'setting_key' => 'model_default',
                'setting_value' => 'SARIMA',
                'setting_type' => 'string',
                'description' => 'Model forecasting default (SARIMA, Prophet, ExponentialSmoothing)',
            ],
            [
                'setting_key' => 'confidence_level',
                'setting_value' => '0.95',
                'setting_type' => 'string',
                'description' => 'Confidence level untuk interval prediksi (95%)',
            ],
            [
                'setting_key' => 'min_data_points',
                'setting_value' => '52',
                'setting_type' => 'integer',
                'description' => 'Minimum data point untuk forecasting (52 minggu = 1 tahun)',
            ],
            [
                'setting_key' => 'auto_retrain',
                'setting_value' => 'true',
                'setting_type' => 'boolean',
                'description' => 'Otomatis retrain model setiap ada data baru',
            ],
            [
                'setting_key' => 'max_upload_size',
                'setting_value' => '10240',
                'setting_type' => 'integer',
                'description' => 'Maximum upload file size in KB (10MB)',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['setting_key' => $setting['setting_key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}