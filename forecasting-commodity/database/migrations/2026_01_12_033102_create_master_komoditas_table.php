<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('master_komoditas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_komoditas', 100);
            $table->string('nama_varian', 100)->nullable();
            $table->string('satuan', 50)->default('Kg');
            $table->integer('kuantitas')->default(1);
            $table->timestamps();
            
            $table->index(['nama_komoditas', 'nama_varian']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('master_komoditas');
    }
};