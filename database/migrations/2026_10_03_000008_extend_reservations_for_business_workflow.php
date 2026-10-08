<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('preferred_visit_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->text('decline_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('item_ready_confirmed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['preferred_visit_at', 'expires_at', 'decline_reason', 'cancellation_reason', 'item_ready_confirmed_at', 'reminder_sent_at', 'completed_at', 'no_show_at']);
        });
    }
};
