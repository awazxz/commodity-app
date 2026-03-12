<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Rentang tanggal
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Forecast horizon
            $table->unsignedSmallInteger('forecast_weeks')->default(12);

            // Hyperparameter Prophet
            $table->decimal('changepoint_prior_scale', 6, 4)->default(0.05);
            $table->decimal('seasonality_prior_scale', 6, 2)->default(10.00);
            $table->string('seasonality_mode', 20)->default('multiplicative');
            $table->boolean('weekly_seasonality')->default(false);
            $table->boolean('yearly_seasonality')->default(false);

            $table->timestamps();

            // Satu baris per user
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};