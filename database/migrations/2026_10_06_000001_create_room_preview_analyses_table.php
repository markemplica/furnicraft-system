<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_preview_analyses', function (Blueprint $table) {
            $table->id();
            $table->char('image_sha256', 64);
            $table->string('pipeline_version', 20);
            $table->string('status', 20)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('analysis')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
            $table->unique(['image_sha256', 'pipeline_version'], 'room_preview_analysis_hash_version_uq');
            $table->index(['status', 'updated_at'], 'room_preview_analysis_status_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_preview_analyses');
    }
};
