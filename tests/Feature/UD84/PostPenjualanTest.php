<?php

namespace Tests\Feature\UD84;

use DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * DatabaseTransactions — NOT RefreshDatabase. The controller opens its own
 * transaction; MySQL nests that as a savepoint and the outer rollback still
 * removes everything this test writes.
 */
class PostPenjualanTest extends TestCase
{
    use DatabaseTransactions;

    private function seedProduct(string $tipe, int $perItem = 10): object
    {
        $id = DB::table('ud84_master_produk')->insertGetId([
            'NAMA'            => 'PRODUK TES '.uniqid(),
            'STOK'            => 1000,
            'TIPE'            => $tipe,
            'STATUS_JUAL'     => 'Katalog dan Penjualan',
            'DISTRIBUTOR'     => 'TES',
            'HARGA_PABRIK'    => 5000,
            'HARGA_JUAL'      => 10000,
            'JUMLAH_PER_ITEM' => $perItem,
            'HARGA_PER_ITEM'  => 1200,
        ]);

        return DB::table('ud84_master_produk')->where('ID', $id)->first();
    }

    private function sell(object $product, string $tipeJual): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/UD84/Penjualan/Saving-Receipt', [
            'MEMBER'      => 'UMUM',
            'DP'          => 0,
            'CASH'        => 20000,
            'POTONGAN'    => 0,
            'JATUH_TEMPO' => null,
            'TOTAL'       => 20000,
            'KETERANGAN'  => 'Test',
            'CART'        => [[
                'ID'              => $product->ID,
                'NAMA'            => $product->NAMA,
                'QUANTITY'        => 2,
                'TOTAL'           => 20000,
                'HARGA_ASLI'      => 10000,
                'POTONGAN_PERSEN' => 0,
                'POTONGAN_RUPIAH' => 0,
                'TIPE'            => $tipeJual,
            ]],
        ]);
    }

    public function test_response_returns_the_transaction_unique(): void
    {
        $product  = $this->seedProduct('Set');
        $response = $this->sell($product, 'Satuan');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $unique = $response->json('data.UNIQUE');
        $this->assertIsString($unique);
        $this->assertNotEmpty($unique);
        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique]);
    }

    public function test_satuan_sale_stores_the_master_unit(): void
    {
        $product = $this->seedProduct('Set');
        $unique  = $this->sell($product, 'Satuan')->json('data.UNIQUE');

        $line = DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->first();

        $this->assertSame('Set', $line->SATUAN);
    }

    public function test_pieces_sale_stores_pcs(): void
    {
        $product = $this->seedProduct('Karton');
        $unique  = $this->sell($product, 'Pieces')->json('data.UNIQUE');

        $line = DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->first();

        $this->assertSame('Pcs', $line->SATUAN);
    }

    /** Selling one Satuan must still deduct JUMLAH_PER_ITEM pieces. */
    public function test_stock_arithmetic_is_unchanged(): void
    {
        $product = $this->seedProduct('Set', 10);
        $this->sell($product, 'Satuan');

        $after = DB::table('ud84_master_produk')->where('ID', $product->ID)->first();

        $this->assertSame(1000 - (2 * 10), (int)$after->STOK);
    }
}
