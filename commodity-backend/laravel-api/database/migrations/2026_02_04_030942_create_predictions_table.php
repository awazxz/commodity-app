<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
    
            $table->foreignId('commodity_id')
            ->constrained('commodities')
            ->onDelete('cascade');
    
            $table->date('tanggal');
            $table->decimal('harga_prediksi', 15, 2);
            $table->decimal('confidence_interval_lower', 15, 2)->nullable();
            $table->decimal('confidence_interval_upper', 15, 2)->nullable();
    
            $table->integer('tahun');
            $table->integer('bulan');
            $table->integer('minggu');
    
            $table->string('model_used', 100)->nullable()->comment('SARIMA, Prophet, etc');
    
    // Metrics
            $table->decimal('mae', 15, 4)->nullable();
            $table->decimal('mape', 15, 4)->nullable();
            $table->decimal('rmse', 15, 4)->nullable();
    
            $table->foreignId('created_by')
            ->nullable()
            ->constrained('users')
            ->onDelete('set null');
    
            $table->timestamps();
    
    // Indexes
            $table->index('commodity_id');
            $table->index('tanggal');
            $table->index(['tahun', 'bulan']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('predictions');
    }
};