<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('furniture_products', function (Blueprint $table) {
            $table->boolean('dimensions_estimated')->default(false)->after('height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('furniture_products', function (Blueprint $table) {
            $table->dropColumn('dimensions_estimated');
        });
    }
};