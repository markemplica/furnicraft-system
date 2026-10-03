<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_images', function (Blueprint $table) {
            $table->string('analysis_status')->default('fallback')->index();
            $table->unsignedSmallInteger('analysis_attempts')->default(0);
            $table->text('analysis_error')->nullable();
            $table->timestamp('analysis_requested_at')->nullable();
        });

        DB::table('room_images')->orderBy('id')->each(function ($room): void {
            $analysis = json_decode((string) $room->analysis, true);
            DB::table('room_images')->where('id', $room->id)->update([
                'analysis_status' => data_get($analysis, 'gemini_used') === true ? 'completed' : 'fallback',
                'analysis_requested_at' => $room->analyzed_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('room_images', function (Blueprint $table) {
            $table->dropIndex(['analysis_status']);
            $table->dropColumn(['analysis_status', 'analysis_attempts', 'analysis_error', 'analysis_requested_at']);
        });
    }
};
