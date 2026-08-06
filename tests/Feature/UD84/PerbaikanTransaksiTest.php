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
class PerbaikanTransaksiTest extends TestCase
{
    use DatabaseTransactions;

    private function seedProduct(array $overrides = []): object
    {
        $id = DB::table('ud84_master_produk')->insertGetId(array_merge([
            'NAMA'            => 'PRODUK KOREKSI '.uniqid(),
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

    private function seedMember(string $nama, int $poin = 0): int
    {
        return (int) DB::table('ud84_member')->insertGetId([
            'NAMA'       => $nama,
            'POINT'      => $poin,
            'CREATED_AT' => '2026-08-06 10:00:00',
        ]);
    }

    /** Lines default to an OLD created date, so date preservation is observable. */
    private function seedSale(array $rekap = [], array $lines = []): string
    {
        $unique = 'koreksi'.uniqid();

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
            'CREATED_AT' => '2026-03-01 09:00:00',
        ], $rekap));

        foreach ($lines as $line) {
            DB::table('ud84_penjualan_detail')->insert(array_merge([
                'UNIQUE'          => $unique,
                'KODE'            => null,
                'NAMA'            => 'BARANG TES',
                'SATUAN'          => 'Pcs',
                'JUMLAH'          => 1,
                'HARGA_ASLI'      => 0,
                'HARGA_TERJUAL'   => 0,
                'POTONGAN_PERSEN' => 0,
                'POTONGAN_RUPIAH' => 0,
                'CREATED_AT'      => '2026-03-01 09:00:00',
            ], $line));
        }

        return $unique;
    }

    private function koreksiBlock(string $unique): array
    {
        return $this->getJson('/api/UD84/Daftar-Transaksi/Detail-Transaksi/'.$unique)
            ->assertStatus(200)->json('data.KOREKSI');
    }

    public function test_a_sale_whose_lines_all_resolve_allows_item_editing(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale([], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs', 'JUMLAH' => 2,
        ]]);

        $koreksi = $this->koreksiBlock($unique);

        $this->assertTrue($koreksi['DAPAT_UBAH_ITEM']);
        $this->assertNull($koreksi['ALASAN']);
    }

    public function test_a_line_without_a_unit_blocks_item_editing_and_says_which(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale([], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => null, 'JUMLAH' => 2,
        ]]);

        $koreksi = $this->koreksiBlock($unique);

        $this->assertFalse($koreksi['DAPAT_UBAH_ITEM']);
        $this->assertStringContainsString($produk->NAMA, $koreksi['ALASAN']);
        $this->assertStringContainsString('satuan', $koreksi['ALASAN']);
    }

    public function test_a_line_whose_product_is_gone_blocks_item_editing(): void
    {
        $unique = $this->seedSale([], [[
            'KODE' => 999999, 'NAMA' => 'PRODUK HILANG', 'SATUAN' => 'Pcs', 'JUMLAH' => 1,
        ]]);

        $koreksi = $this->koreksiBlock($unique);

        $this->assertFalse($koreksi['DAPAT_UBAH_ITEM']);
        $this->assertStringContainsString('PRODUK HILANG', $koreksi['ALASAN']);
    }

    public function test_a_line_with_no_product_reference_blocks_item_editing(): void
    {
        $unique = $this->seedSale([], [[
            'KODE' => null, 'NAMA' => 'BARANG LAMA', 'SATUAN' => 'Pcs', 'JUMLAH' => 1,
        ]]);

        $this->assertFalse($this->koreksiBlock($unique)['DAPAT_UBAH_ITEM']);
    }

    public function test_a_sale_with_no_lines_at_all_blocks_item_editing(): void
    {
        $unique = $this->seedSale();

        $this->assertFalse($this->koreksiBlock($unique)['DAPAT_UBAH_ITEM']);
    }
}
