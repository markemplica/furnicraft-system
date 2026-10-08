<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_preview_generations', function (Blueprint $table) {
            $table->char('cache_key', 64)->nullable()->index('room_preview_generation_cache_key_idx');
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('confidence_label', 32)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->json('metrics_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->index(['status', 'updated_at'], 'room_preview_generation_status_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('room_preview_generations', function (Blueprint $table) {
            $table->dropIndex('room_preview_generation_cache_key_idx');
            $table->dropIndex('room_preview_generation_status_updated_idx');
            $table->dropColumn(['cache_key', 'confidence', 'confidence_label', 'failure_code', 'metrics_json', 'completed_at']);
        });
    }
};
