<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropUnique('user_preferences_user_id_unique');
            $table->unsignedBigInteger('komoditas_id')->default(0)->after('user_id');
            $table->unique(['user_id', 'komoditas_id'], 'user_komoditas_unique');
        });
    }
    public function down()
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropUnique('user_komoditas_unique');
            $table->dropColumn('komoditas_id');
            $table->unique(['user_id'], 'user_preferences_user_id_unique');
        });
    }
};
