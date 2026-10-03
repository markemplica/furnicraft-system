<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_preview_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('furniture_product_id')->constrained('furniture_products')->cascadeOnDelete();
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();
            $table->unique(['room_image_id', 'furniture_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_preview_generations');
    }
};
