<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version-control record for the SATUAN column.
 *
 * This migration is intentionally NOT executed. The migrations table records
 * only the project's original Laravel 9/10-era migrations; the Laravel
 * 11-style files now in this directory are not recorded there, so running
 * `artisan migrate` would attempt create_users_table against the existing
 * `users` table and fail. The change is applied by executing
 * database/sql/2026_08_05_add_satuan_to_ud84_penjualan_detail.sql directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ud84_penjualan_detail', function (Blueprint $table) {
            $table->string('SATUAN', 20)->nullable()->after('NAMA');
        });
    }

    public function down(): void
    {
        Schema::table('ud84_penjualan_detail', function (Blueprint $table) {
            $table->dropColumn('SATUAN');
        });
    }
};
