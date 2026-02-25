<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commodities', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('satuan', 50)->default('Kg');
            $table->string('kategori', 100)->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
    
    // Indexes
            $table->index('nama');
            $table->index('kategori');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('komoditas');
    }
};