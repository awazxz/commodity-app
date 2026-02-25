<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('price_forecasts', function (Blueprint $table) {
            $table->id();

            // Relasi ke master komoditas
            $table->foreignId('komoditas_id')
                  ->constrained('master_komoditas')
                  ->cascadeOnDelete();

            // Tanggal hasil prediksi
            $table->date('tanggal');

            // Hasil forecasting Prophet
            $table->double('yhat');        // nilai prediksi
            $table->double('yhat_lower');  // batas bawah
            $table->double('yhat_upper');  // batas atas

            $table->timestamps();

            // Mencegah duplikasi forecast
            $table->unique(['komoditas_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('price_forecasts');
    }
};
