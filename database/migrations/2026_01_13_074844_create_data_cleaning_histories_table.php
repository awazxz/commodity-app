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
        Schema::create('data_cleaning_histories', function (Blueprint $table) {
            $table->id();
            $table->string('commodity');
            $table->date('date')->nullable();
            $table->string('issue'); // Outlier | Missing Value
            $table->double('old_value')->nullable();
            $table->double('new_value')->nullable();
            $table->string('method'); // mean | median | remove
            $table->timestamp('created_at')->useCurrent();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('data_cleaning_histories');
    }
};
