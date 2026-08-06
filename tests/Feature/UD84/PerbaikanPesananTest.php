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
class PerbaikanPesananTest extends TestCase
{
    use DatabaseTransactions;

    private function seedProduct(array $overrides = []): object
    {
        $id = DB::table('ud84_master_produk')->insertGetId(array_merge([
            'NAMA'            => 'PRODUK PESANAN '.uniqid(),
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

    private function seedSalesperson(string $status = 'Aktif'): int
    {
        return (int) DB::table('ud84_sales')->insertGetId([
            'NAMA'       => 'Sales Pesanan '.uniqid(),
            'STATUS'     => $status,
            'CREATED_AT' => '2026-08-06 10:00:00',
        ]);
    }

    /** Lines default to an OLD created date, so date preservation is observable. */
    private function seedOrder(array $rekap = [], array $lines = []): string
    {
        $kode = 'ubah'.uniqid();

        DB::table('ud84_pesanan_rekap')->insert(array_merge([
            'NAMA'       => 'Pelanggan Tes',
            'WHATSAPP'   => '08123456789',
            'SALES'      => null,
            'CATATAN'    => 'Catatan awal',
            'VALID'      => null,
            'KODE'       => $kode,
            'CREATED_AT' => '2026-03-01 09:00:00',
        ], $rekap));

        foreach ($lines as $line) {
            DB::table('ud84_pesanan_detail')->insert(array_merge([
                'KODE'       => $kode,
                'KODE_ITEM'  => 0,
                'JUMLAH'     => 1,
                'CREATED_AT' => '2026-03-01 09:00:00',
            ], $line));
        }

        return $kode;
    }

    public function test_the_order_list_carries_the_salesperson_id(): void
    {
        $salesId = $this->seedSalesperson();
        $kode    = $this->seedOrder(['SALES' => $salesId]);

        $rows = $this->postJson('/api/UD84/Pesanan/Retrieve', [
            'start' => '2026-03-01 00:00:00',
            'end'   => '2026-03-01 23:59:59',
        ])->assertStatus(200)->json('data');

        $found = collect($rows)->firstWhere('KODE', $kode);

        $this->assertNotNull($found);
        $this->assertSame($salesId, $found['SALES_ID']);
    }

    public function test_order_items_carry_their_product_id(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $items = $this->postJson('/api/UD84/Pesanan/Retrieve-Items', ['ID' => $kode])
            ->assertStatus(200)->json('data');

        $this->assertSame($produk->ID, $items[0]['KODE_ITEM']);
        $this->assertTrue($items[0]['ADA']);
    }

    public function test_a_line_whose_product_is_gone_is_marked_not_found(): void
    {
        $kode = $this->seedOrder([], [['KODE_ITEM' => 999999, 'JUMLAH' => 2]]);

        $items = $this->postJson('/api/UD84/Pesanan/Retrieve-Items', ['ID' => $kode])
            ->assertStatus(200)->json('data');

        $this->assertFalse($items[0]['ADA']);
        $this->assertSame(999999, $items[0]['KODE_ITEM']);
        $this->assertSame(2, $items[0]['JUMLAH']);
    }

    public function test_the_order_audit_trail_is_readable_by_order_code(): void
    {
        $kode = $this->seedOrder();

        DB::table('ud84_transaksi_log')->insert([
            'UNIQUE_TRANSAKSI' => $kode,
            'AKSI'             => 'Edit Pesanan',
            'OPERATOR'         => 'Tester',
            'CATATAN_SISTEM'   => 'Contoh',
            'CREATED_AT'       => '2026-08-06 10:00:00',
        ]);

        $rows = $this->postJson('/api/UD84/Pesanan/Riwayat', ['KODE' => $kode])
            ->assertStatus(200)->assertJson(['status' => 'success'])->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('Edit Pesanan', $rows[0]['AKSI']);
    }
}
