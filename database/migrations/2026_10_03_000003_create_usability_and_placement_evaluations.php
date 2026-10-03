<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_usability_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->decimal('sus_score', 5, 2);
            $table->timestamps();
        });

        Schema::create('room_placement_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('floor_detection_correct');
            $table->boolean('zone_appropriate');
            $table->boolean('clearance_respected')->nullable();
            $table->boolean('scale_plausible');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['room_image_id', 'evaluator_id']);
            $table->index(['floor_detection_correct', 'zone_appropriate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_placement_evaluations');
        Schema::dropIfExists('system_usability_responses');
    }
};
