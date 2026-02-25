<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analisis_deskriptif', function (Blueprint $table) {
            $table->id();
            
            // Foreign key ke commodities
            $table->foreignId('commodity_id')
                  ->constrained('commodities')
                  ->onDelete('cascade');
            
            // Periode analisis
            $table->date('periode_start');
            $table->date('periode_end');
            
            // Statistik deskriptif
            $table->decimal('harga_min', 15, 2)->nullable();
            $table->decimal('harga_max', 15, 2)->nullable();
            $table->decimal('harga_mean', 15, 2)->nullable();
            $table->decimal('harga_median', 15, 2)->nullable();
            $table->decimal('std_deviation', 15, 4)->nullable();
            $table->decimal('variance', 15, 4)->nullable();
            
            // Analisis trend & volatilitas
            $table->enum('trend', ['naik', 'turun', 'stabil'])->nullable();
            $table->enum('volatility', ['tinggi', 'sedang', 'rendah'])->nullable();
            
            $table->integer('count_data')->nullable()->comment('Jumlah data point');
            
            $table->timestamps();
            
            // Indexes
            $table->index('commodity_id');
            $table->index(['periode_start', 'periode_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analisis_deskriptif');
    }
};