<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price_data', function (Blueprint $table) {
            $table->id();
            // Nama barang/komoditas
            $table->string('nama_varian'); 
            
            // Satuan (kg, liter, dll) dan jumlah kuantitas
            $table->string('satuan')->nullable();
            $table->string('kuantitas')->nullable(); 
            
            // Data Waktu untuk kebutuhan Forecasting
            $table->integer('tahun');
            $table->string('bulan');
            $table->string('minggu'); // M1, M2, dst
            
            // Harga menggunakan double atau decimal agar presisi
            $table->double('harga', 15, 2); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_data');
    }
};