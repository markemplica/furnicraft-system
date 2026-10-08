<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) return;

        DB::statement('ALTER TABLE furniture_products MODIFY wood_type VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE furniture_products MODIFY varnish_finish VARCHAR(255) NOT NULL');
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) return;

        // Check both columns before either ALTER; MySQL DDL is not transactional.
        $woods = ['Gmelina', 'Mahogany', 'Mangga'];
        $finishes = ['Natural', 'Narra Look', 'Light Narra Look', 'Mahogany', 'Mahogany Red', 'Chocolate Brown', 'Narra / Chocolate Brown', 'Chocolate Brown / Brown Combo'];
        if (DB::table('furniture_products')->whereNotIn('wood_type', $woods)->exists()
            || DB::table('furniture_products')->whereNotIn('varnish_finish', $finishes)->exists()) {
            throw new \RuntimeException('Catalog materials contain values outside the legacy enums. Rollback stopped before changing either column; preserve or explicitly migrate these values first.');
        }

        DB::statement("ALTER TABLE furniture_products MODIFY wood_type ENUM('Gmelina', 'Mahogany', 'Mangga') NOT NULL");
        DB::statement("ALTER TABLE furniture_products MODIFY varnish_finish ENUM('Natural', 'Narra Look', 'Light Narra Look', 'Mahogany', 'Mahogany Red', 'Chocolate Brown', 'Narra / Chocolate Brown', 'Chocolate Brown / Brown Combo') NOT NULL");
    }
};