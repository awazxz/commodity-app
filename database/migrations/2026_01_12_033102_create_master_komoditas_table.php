<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_komoditas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_komoditas');
            $table->string('nama_varian')->nullable();
            $table->string('satuan');
            $table->string('kuantitas');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_komoditas');
    }
};
