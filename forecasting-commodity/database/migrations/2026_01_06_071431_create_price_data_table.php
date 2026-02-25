<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('price_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('komoditas_id');
            $table->date('tanggal');
            $table->decimal('harga', 15, 2);
            $table->enum('status', ['raw', 'cleaned'])->default('raw');
            $table->boolean('is_outlier')->default(false);
            $table->timestamps();
            
            $table->foreign('komoditas_id')
                  ->references('id')
                  ->on('master_komoditas')
                  ->onDelete('cascade');
            
            $table->unique(['komoditas_id', 'tanggal']);
            $table->index('tanggal');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('price_data');
    }
};