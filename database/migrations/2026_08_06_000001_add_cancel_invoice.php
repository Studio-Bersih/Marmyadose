<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Version-control record for the cancel-invoice schema.
 *
 * This migration is intentionally NOT executed. The migrations table records
 * only the project's original Laravel 9/10-era migrations; the Laravel
 * 11-style files now in this directory are not recorded there, so running
 * `artisan migrate` would attempt create_users_table against the existing
 * `users` table and fail. The change is applied by executing
 * database/sql/2026_08_06_add_cancel_invoice.sql directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ud84_penjualan_rekap', function (Blueprint $table) {
            $table->enum('STATUS', ['Aktif', 'Dibatalkan'])->default('Aktif')->after('UNIQUE');
            // Points actually granted, so a cancellation reverses exactly what
            // was given instead of recomputing under a rule that may have
            // changed since. Null means the sale predates this column.
            $table->smallInteger('POIN')->nullable()->after('MEMBER');
        });

        // KODE holds ud84_master_produk.ID (int) but was smallint, capping at
        // 32767. Cancellation resolves the product by KODE to return stock, so
        // a truncated ID would credit the wrong product.
        DB::statement('ALTER TABLE `ud84_penjualan_detail` MODIFY COLUMN `KODE` int(11) DEFAULT NULL');

        Schema::create('ud84_transaksi_log', function (Blueprint $table) {
            $table->bigIncrements('ID');
            $table->string('UNIQUE_TRANSAKSI', 50)->nullable()->index();
            $table->string('AKSI', 30)->nullable();
            $table->string('OPERATOR', 100)->nullable();
            $table->text('ALASAN')->nullable();
            $table->text('CATATAN_SISTEM')->nullable();
            $table->longText('SEBELUM')->nullable();
            $table->longText('SESUDAH')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ud84_transaksi_log');

        Schema::table('ud84_penjualan_rekap', function (Blueprint $table) {
            $table->dropColumn('STATUS');
        });
    }
};
