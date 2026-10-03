<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_images', function (Blueprint $table) {
            $table->timestamp('gemini_consent_at')->nullable()->after('analysis_requested_at');
            $table->string('gemini_consent_version', 40)->nullable()->after('gemini_consent_at');
        });

        Schema::create('privacy_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_audit_events');
        Schema::table('room_images', function (Blueprint $table) {
            $table->dropColumn(['gemini_consent_at', 'gemini_consent_version']);
        });
    }
};
