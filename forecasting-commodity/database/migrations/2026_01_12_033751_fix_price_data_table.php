<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop table lama
        Schema::dropIfExists('price_data');

        // Buat ulang table baru
        Schema::create('price_data', function (Blueprint $table) {
            $table->id();

            $table->foreignId('komoditas_id')
                ->constrained('master_komoditas')
                ->cascadeOnDelete();

            $table->date('tanggal');   // ds
            $table->double('harga');   // y

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_data');
    }
};
