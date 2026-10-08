<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_preview_generations', function (Blueprint $table) {
            $table->json('placement_json')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_preview_generations', function (Blueprint $table) {
            $table->dropColumn('placement_json');
        });
    }
};