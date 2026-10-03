<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->boolean('is_cold_start')->nullable()->after('gemini_used');
            $table->decimal('content_weight', 5, 4)->nullable()->after('is_cold_start');
            $table->decimal('collaborative_weight', 5, 4)->nullable()->after('content_weight');
        });

        Schema::table('recommendation_items', function (Blueprint $table) {
            $table->boolean('relevance')->nullable()->after('explanation');
            $table->string('rejection_reason', 40)->nullable()->after('relevance');
            $table->timestamp('evaluated_at')->nullable()->after('rejection_reason');
            $table->index(['relevance', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_items', function (Blueprint $table) {
            $table->dropIndex(['relevance', 'evaluated_at']);
            $table->dropColumn(['relevance', 'rejection_reason', 'evaluated_at']);
        });

        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropColumn(['is_cold_start', 'content_weight', 'collaborative_weight']);
        });
    }
};
