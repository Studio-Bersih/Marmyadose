<?php

namespace Tests\Feature\UD84;

use DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * DatabaseTransactions — NOT RefreshDatabase. RefreshDatabase runs
 * migrate:fresh, which would drop every ud84_* table; none are covered by
 * migrations, so they would not come back.
 */
class BatalTransaksiTest extends TestCase
{
    use DatabaseTransactions;

    private function seedProduct(array $overrides = []): object
    {
        $id = DB::table('ud84_master_produk')->insertGetId(array_merge([
            'NAMA'            => 'PRODUK BATAL '.uniqid(),
            'STOK'            => 1000,
            'TIPE'            => 'Set',
            'STATUS_JUAL'     => 'Katalog dan Penjualan',
            'DISTRIBUTOR'     => 'TES',
            'HARGA_PABRIK'    => 5000,
            'HARGA_JUAL'      => 10000,
            'JUMLAH_PER_ITEM' => 10,
            'HARGA_PER_ITEM'  => 1200,
        ], $overrides));

        return DB::table('ud84_master_produk')->where('ID', $id)->first();
    }

    private function seedSale(array $rekap = [], array $lines = []): string
    {
        $unique = 'batal'.uniqid();

        DB::table('ud84_penjualan_rekap')->insert(array_merge([
            'UNIQUE'     => $unique,
            'STATUS'     => 'Aktif',
            'NAMA'       => 'UMUM',
            'CASH'       => 0,
            'KEMBALIAN'  => 0,
            'DP'         => 0,
            'POTONGAN'   => 0,
            'TOTAL'      => 0,
            'MEMBER'     => 'UMUM',
            'POIN'       => 0,
            'CREATED_AT' => '2026-08-06 10:00:00',
        ], $rekap));

        foreach ($lines as $line) {
            DB::table('ud84_penjualan_detail')->insert(array_merge([
                'UNIQUE'          => $unique,
                'KODE'            => null,
                'NAMA'            => 'BARANG TES',
                'SATUAN'          => null,
                'JUMLAH'          => 1,
                'HARGA_ASLI'      => 0,
                'HARGA_TERJUAL'   => 0,
                'POTONGAN_PERSEN' => 0,
                'POTONGAN_RUPIAH' => 0,
                'CREATED_AT'      => '2026-08-06 10:00:00',
            ], $line));
        }

        return $unique;
    }

    private function cancel(string $unique, string $alasan = 'Salah input')
    {
        return $this->postJson('/api/UD84/Daftar-Transaksi/Batal', [
            'KODE'     => $unique,
            'ALASAN'   => $alasan,
            'OPERATOR' => 'Tester',
        ]);
    }

    public function test_cancel_marks_the_transaction_as_cancelled(): void
    {
        $unique = $this->seedSale();

        $this->cancel($unique)->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'STATUS' => 'Dibatalkan']);
    }

    public function test_cancelling_twice_is_refused(): void
    {
        $unique = $this->seedSale();
        $this->cancel($unique);

        $this->cancel($unique)->assertStatus(200)->assertJson(['status' => 'error']);
    }

    public function test_a_blank_reason_is_refused(): void
    {
        $unique = $this->seedSale();

        $this->cancel($unique, '   ')->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'STATUS' => 'Aktif']);
    }

    public function test_an_unknown_transaction_is_refused(): void
    {
        $this->cancel('tidak-ada')->assertStatus(200)->assertJson(['status' => 'error']);
    }

    public function test_a_pieces_line_returns_its_quantity_to_stock(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale([], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs', 'JUMLAH' => 7,
        ]]);

        $this->cancel($unique);

        $this->assertSame(107, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
    }

    /** STOK is counted in pieces, so a Set of 10 returns 10 pieces per unit. */
    public function test_a_satuan_line_returns_quantity_times_jumlah_per_item(): void
    {
        $produk = $this->seedProduct(['STOK' => 100, 'TIPE' => 'Set', 'JUMLAH_PER_ITEM' => 10]);
        $unique = $this->seedSale([], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Set', 'JUMLAH' => 3,
        ]]);

        $this->cancel($unique);

        $this->assertSame(130, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
    }

    public function test_a_line_without_satuan_restores_nothing_and_is_reported(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale([], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => null, 'JUMLAH' => 5,
        ]]);

        $response = $this->cancel($unique);

        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertContains($produk->NAMA, $response->json('data.GAGAL_RESTOK'));

        $catatan = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $unique)->value('CATATAN_SISTEM');
        $this->assertStringContainsString('satuan penjualan tidak tercatat', $catatan);
    }

    public function test_a_line_whose_product_is_gone_restores_nothing_and_is_reported(): void
    {
        $unique = $this->seedSale([], [[
            'KODE' => 999999, 'NAMA' => 'PRODUK SUDAH DIHAPUS '.uniqid(), 'SATUAN' => 'Pcs', 'JUMLAH' => 5,
        ]]);

        $response = $this->cancel($unique);

        $this->assertNotEmpty($response->json('data.GAGAL_RESTOK'));

        $catatan = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $unique)->value('CATATAN_SISTEM');
        $this->assertStringContainsString('produk tidak ditemukan', $catatan);
    }

    public function test_a_reversing_stock_log_is_written_and_the_original_is_untouched(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);

        DB::table('ud84_logs')->insert([
            'KODE_ITEM' => $produk->ID, 'NAMA_ITEM' => $produk->NAMA, 'ASAL' => 'Retail',
            'MASUK' => 0, 'KELUAR' => 7, 'STOK_FINAL' => 100, 'CREATED_AT' => '2026-08-06 10:00:00',
        ]);

        $unique = $this->seedSale([], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs', 'JUMLAH' => 7,
        ]]);

        $this->cancel($unique);

        $this->assertDatabaseHas('ud84_logs', [
            'KODE_ITEM' => $produk->ID, 'ASAL' => 'Batal Transaksi', 'MASUK' => 7, 'KELUAR' => 0, 'STOK_FINAL' => 107,
        ]);

        // the sale's original row survives unedited
        $this->assertDatabaseHas('ud84_logs', [
            'KODE_ITEM' => $produk->ID, 'ASAL' => 'Retail', 'KELUAR' => 7,
        ]);
    }

    public function test_points_granted_by_the_sale_are_deducted(): void
    {
        $nama = 'Member Batal '.uniqid();
        DB::table('ud84_member')->insert([
            'UNIQUE' => 'm'.uniqid(), 'NAMA' => $nama, 'POINT' => 10, 'CREATED_AT' => '2026-08-06 10:00:00',
        ]);

        $unique = $this->seedSale(['NAMA' => $nama, 'MEMBER' => $nama, 'CASH' => 1500000, 'POIN' => 3]);

        $this->cancel($unique);

        $this->assertSame(7, (int) DB::table('ud84_member')->where('NAMA', $nama)->value('POINT'));
    }

    /** A member who has since spent points must not be driven negative. */
    public function test_point_deduction_clamps_at_zero(): void
    {
        $nama = 'Member Minim '.uniqid();
        DB::table('ud84_member')->insert([
            'UNIQUE' => 'm'.uniqid(), 'NAMA' => $nama, 'POINT' => 1, 'CREATED_AT' => '2026-08-06 10:00:00',
        ]);

        $unique = $this->seedSale(['NAMA' => $nama, 'MEMBER' => $nama, 'CASH' => 1500000, 'POIN' => 3]);

        $this->cancel($unique);

        $this->assertSame(0, (int) DB::table('ud84_member')->where('NAMA', $nama)->value('POINT'));

        $catatan = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $unique)->value('CATATAN_SISTEM');
        $this->assertStringContainsString('saldo tidak mencukupi', $catatan);
    }

    /** Sales predating the POIN column fall back to recomputing from CASH. */
    public function test_a_sale_without_recorded_points_recomputes_from_cash(): void
    {
        $nama = 'Member Lama '.uniqid();
        DB::table('ud84_member')->insert([
            'UNIQUE' => 'm'.uniqid(), 'NAMA' => $nama, 'POINT' => 10, 'CREATED_AT' => '2026-08-06 10:00:00',
        ]);

        $unique = $this->seedSale(['NAMA' => $nama, 'MEMBER' => $nama, 'CASH' => 1500000, 'POIN' => null]);

        $this->cancel($unique);

        // 1.500.000 / 500.000 = 3
        $this->assertSame(7, (int) DB::table('ud84_member')->where('NAMA', $nama)->value('POINT'));

        $catatan = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $unique)->value('CATATAN_SISTEM');
        $this->assertStringContainsString('dihitung ulang', $catatan);
    }

    public function test_the_audit_row_records_operator_reason_and_snapshots(): void
    {
        $unique = $this->seedSale(['TOTAL' => 50000]);

        $this->cancel($unique, 'Pelanggan batal ambil');

        $log = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $unique)->first();

        $this->assertSame('Batal', $log->AKSI);
        $this->assertSame('Tester', $log->OPERATOR);
        $this->assertSame('Pelanggan batal ambil', $log->ALASAN);

        $sebelum = json_decode($log->SEBELUM, true);
        $this->assertSame('Aktif', $sebelum['rekap']['STATUS']);

        $sesudah = json_decode($log->SESUDAH, true);
        $this->assertSame('Dibatalkan', $sesudah['rekap']['STATUS']);
    }

    public function test_cancelling_does_not_alter_the_money_fields(): void
    {
        $unique = $this->seedSale(['CASH' => 90000, 'DP' => 10000, 'TOTAL' => 100000, 'POTONGAN' => 5000]);

        $this->cancel($unique);

        $row = DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->first();

        $this->assertSame(90000, (int) $row->CASH);
        $this->assertSame(10000, (int) $row->DP);
        $this->assertSame(100000, (int) $row->TOTAL);
        $this->assertSame(5000, (int) $row->POTONGAN);
    }

    public function test_update_dp_is_refused_on_a_cancelled_transaction(): void
    {
        $unique = $this->seedSale(['TOTAL' => 100000, 'DP' => 10000]);
        $this->cancel($unique);

        $this->postJson('/api/UD84/Daftar-Transaksi/Update-DP', [
            'KODE' => $unique, 'DP' => 5000, 'OLD_DP' => 10000,
        ])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(10000, (int) DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->value('DP'));
    }

    public function test_the_audit_trail_is_readable(): void
    {
        $unique = $this->seedSale();
        $this->cancel($unique, 'Alasan uji');

        $data = $this->postJson('/api/UD84/Daftar-Transaksi/Riwayat', ['KODE' => $unique])->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Alasan uji', $data[0]['ALASAN']);
    }
}
