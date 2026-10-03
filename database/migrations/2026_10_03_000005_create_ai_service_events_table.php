<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_service_events', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 40)->index();
            $table->string('operation', 60)->index();
            $table->string('status', 20)->index();
            $table->string('model', 120)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->string('failure_code', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_service_events');
    }
};
