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
        Schema::create('laporan_export', function (Blueprint $table) {
            $table->id();
            
            // User yang export
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            // Komoditas (nullable karena bisa export semua)
            $table->foreignId('commodity_id')
                  ->nullable()
                  ->constrained('commodities')
                  ->onDelete('set null');
            
            // Format export
            $table->enum('format', ['pdf', 'excel', 'csv']);
            
            // Periode laporan
            $table->date('periode_start')->nullable();
            $table->date('periode_end')->nullable();
            
            // File info
            $table->string('filename', 255)->nullable();
            $table->string('filepath', 500)->nullable();
            $table->integer('filesize')->nullable()->comment('Size in bytes');
            
            // Status
            $table->enum('status', ['processing', 'completed', 'failed'])
                  ->default('processing');
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_export');
    }
};