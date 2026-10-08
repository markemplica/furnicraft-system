<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });

        DB::table('reservations')->whereNotNull('completed_at')->update(['status' => 'completed']);
        DB::table('reservations')->whereNull('completed_at')->whereNotNull('no_show_at')->update(['status' => 'no_show']);
    }

    public function down(): void
    {
        if (DB::table('reservations')->whereIn('status', ['completed', 'no_show'])->exists()) {
            throw new \RuntimeException('Resolve completed and no-show reservations before rolling back their status migration.');
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'declined', 'cancelled'])->default('pending')->change();
        });
    }
};
