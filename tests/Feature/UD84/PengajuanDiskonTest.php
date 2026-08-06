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
class PengajuanDiskonTest extends TestCase
{
    use DatabaseTransactions;

    private function seedProduct(): object
    {
        $id = DB::table('ud84_master_produk')->insertGetId([
            'NAMA'            => 'PRODUK DISKON '.uniqid(),
            'STOK'            => 100,
            'TIPE'            => 'Pcs',
            'STATUS_JUAL'     => 'Katalog dan Penjualan',
            'DISTRIBUTOR'     => 'TES',
            'HARGA_PABRIK'    => 5000,
            'HARGA_JUAL'      => 12000,
            'JUMLAH_PER_ITEM' => 1,
            'HARGA_PER_ITEM'  => 12000,
        ]);

        return DB::table('ud84_master_produk')->where('ID', $id)->first();
    }

    /** Places an order through the real public endpoint. */
    private function pesan(array $carts, string $nama = 'Pelanggan Diskon')
    {
        return $this->postJson('/api/UD84/Penjualan/Order-Online', [
            'NAMA'     => $nama,
            'WHATSAPP' => '08123456789',
            'SALES'    => null,
            'NOTES'    => 'Uji diskon',
            'CARTS'    => $carts,
        ]);
    }

    private function kodePesananTerakhir(): string
    {
        return (string) DB::table('ud84_pesanan_rekap')->orderByDesc('ID')->value('KODE');
    }

    public function test_a_request_written_on_a_line_is_stored_against_it(): void
    {
        $satu = $this->seedProduct();
        $dua  = $this->seedProduct();

        $this->pesan([
            ['ID' => $satu->ID, 'QUANTITY' => 2, 'DISKON' => 'minta 5%'],
            ['ID' => $dua->ID,  'QUANTITY' => 1, 'DISKON' => ''],
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        $kode = $this->kodePesananTerakhir();

        $this->assertSame('minta 5%', DB::table('ud84_pesanan_detail')
            ->where('KODE', $kode)->where('KODE_ITEM', $satu->ID)->value('DISKON'));

        // An empty box is "no request" -- one value, not two.
        $this->assertNull(DB::table('ud84_pesanan_detail')
            ->where('KODE', $kode)->where('KODE_ITEM', $dua->ID)->value('DISKON'));
    }

    public function test_an_order_placed_without_the_field_at_all_still_works(): void
    {
        $produk = $this->seedProduct();

        // The old cart payload, from a browser that has not reloaded.
        $this->pesan([['ID' => $produk->ID, 'QUANTITY' => 3]])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertNull(DB::table('ud84_pesanan_detail')
            ->where('KODE', $this->kodePesananTerakhir())->value('DISKON'));
    }

    public function test_the_item_list_returns_the_request(): void
    {
        $produk = $this->seedProduct();
        $this->pesan([['ID' => $produk->ID, 'QUANTITY' => 1, 'DISKON' => 'tolong dibantu bu']]);

        $items = $this->postJson('/api/UD84/Pesanan/Retrieve-Items', ['ID' => $this->kodePesananTerakhir()])
            ->assertStatus(200)->json('data');

        $this->assertSame('tolong dibantu bu', $items[0]['DISKON']);
    }

    public function test_the_order_list_flags_an_order_carrying_a_request(): void
    {
        $produk = $this->seedProduct();
        $this->pesan([['ID' => $produk->ID, 'QUANTITY' => 1, 'DISKON' => 'minta 10rb']]);
        $dengan = $this->kodePesananTerakhir();

        $this->pesan([['ID' => $produk->ID, 'QUANTITY' => 1]]);
        $tanpa = $this->kodePesananTerakhir();

        $rows = $this->postJson('/api/UD84/Pesanan/Retrieve', [
            'start' => now()->startOfDay()->toDateTimeString(),
            'end'   => now()->endOfDay()->toDateTimeString(),
        ])->assertStatus(200)->json('data');

        $this->assertTrue(collect($rows)->firstWhere('KODE', $dengan)['ADA_DISKON']);
        $this->assertFalse(collect($rows)->firstWhere('KODE', $tanpa)['ADA_DISKON']);
    }

    public function test_editing_an_orders_quantities_leaves_its_requests_intact(): void
    {
        $produk = $this->seedProduct();
        $this->pesan([['ID' => $produk->ID, 'QUANTITY' => 2, 'DISKON' => 'minta 5%']]);
        $kode = $this->kodePesananTerakhir();

        $baris = DB::table('ud84_pesanan_detail')->where('KODE', $kode)->first();

        // The panel's order editor reconciles lines in place; a request must
        // live through an admin adjusting quantities.
        $this->postJson('/api/UD84/Pesanan/Update', [
            'KODE'     => $kode,
            'NAMA'     => 'Pelanggan Diskon',
            'WHATSAPP' => '08123456789',
            'SALES'    => null,
            'CATATAN'  => 'Uji diskon',
            'ITEMS'    => [['ID' => $baris->ID, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 7]],
            'OPERATOR' => 'Tester',
            'ALASAN'   => '',
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        $sesudah = DB::table('ud84_pesanan_detail')->where('ID', $baris->ID)->first();

        $this->assertSame(7, (int) $sesudah->JUMLAH);
        $this->assertSame('minta 5%', $sesudah->DISKON);
    }

    public function test_a_line_the_admin_adds_carries_no_request(): void
    {
        $produk = $this->seedProduct();
        $baru   = $this->seedProduct();
        $this->pesan([['ID' => $produk->ID, 'QUANTITY' => 2, 'DISKON' => 'minta 5%']]);
        $kode  = $this->kodePesananTerakhir();
        $baris = DB::table('ud84_pesanan_detail')->where('KODE', $kode)->first();

        $this->postJson('/api/UD84/Pesanan/Update', [
            'KODE'     => $kode,
            'NAMA'     => 'Pelanggan Diskon',
            'WHATSAPP' => '08123456789',
            'SALES'    => null,
            'CATATAN'  => 'Uji diskon',
            'ITEMS'    => [
                ['ID' => $baris->ID, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 2],
                ['KODE_ITEM' => $baru->ID, 'JUMLAH' => 1],
            ],
            'OPERATOR' => 'Tester',
            'ALASAN'   => '',
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        // Nobody asked for a discount on the line the admin added.
        $this->assertNull(DB::table('ud84_pesanan_detail')
            ->where('KODE', $kode)->where('KODE_ITEM', $baru->ID)->value('DISKON'));
    }
}
