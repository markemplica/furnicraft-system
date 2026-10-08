<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_images', function (Blueprint $table) {
            $table->char('image_sha256', 64)->nullable()->index();
            $table->unsignedSmallInteger('image_width_px')->nullable();
            $table->unsignedSmallInteger('image_height_px')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_images', function (Blueprint $table) {
            $table->dropIndex(['image_sha256']);
            $table->dropColumn(['image_sha256', 'image_width_px', 'image_height_px']);
        });
    }
};