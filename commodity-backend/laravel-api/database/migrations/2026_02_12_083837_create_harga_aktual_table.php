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
        Schema::create('harga_aktual', function (Blueprint $table) {
            $table->id();
            
            // Foreign key ke commodities (komoditas)
            $table->foreignId('commodity_id')
                  ->constrained('commodities')
                  ->onDelete('cascade');
            
            $table->date('tanggal');
            $table->decimal('harga', 15, 2);
            
            // Auto-calculated fields
            $table->integer('tahun');
            $table->integer('bulan');
            $table->integer('minggu');
            
            $table->string('source', 100)->nullable()->comment('import, manual, api');
            
            // User tracking
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['commodity_id', 'tanggal']);
            
            // Indexes untuk performance
            $table->index('commodity_id');
            $table->index('tanggal');
            $table->index(['tahun', 'bulan']);
            $table->index(['tahun', 'minggu']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harga_aktual');
    }
};