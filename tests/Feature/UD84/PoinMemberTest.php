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
class PoinMemberTest extends TestCase
{
    use DatabaseTransactions;

    private function seedMember(int $poin = 0): object
    {
        $id = DB::table('ud84_member')->insertGetId([
            'NAMA'       => 'MEMBER POIN '.uniqid(),
            'LOKASI'     => 'Singosari',
            'ALAMAT'     => 'Jl. Tes',
            'WHATSAPP'   => '08123456789',
            'POINT'      => $poin,
            'CREATED_AT' => '2026-08-07 10:00:00',
        ]);

        return DB::table('ud84_member')->where('ID', $id)->first();
    }

    private function seedProduct(): object
    {
        $id = DB::table('ud84_master_produk')->insertGetId([
            'NAMA'            => 'PRODUK POIN '.uniqid(),
            'STOK'            => 1000,
            'TIPE'            => 'Pcs',
            'STATUS_JUAL'     => 'Katalog dan Penjualan',
            'DISTRIBUTOR'     => 'TES',
            'HARGA_PABRIK'    => 5000,
            'HARGA_JUAL'      => 10000,
            'JUMLAH_PER_ITEM' => 1,
            'HARGA_PER_ITEM'  => 10000,
        ]);

        return DB::table('ud84_master_produk')->where('ID', $id)->first();
    }

    /** Rings up a sale through the real POS endpoint, paid in cash. */
    private function jual(object $member, int $cash)
    {
        $produk = $this->seedProduct();

        return $this->postJson('/api/UD84/Penjualan/Saving-Receipt', [
            'DP'          => 0,
            'CASH'        => $cash,
            'POTONGAN'    => 0,
            'JATUH_TEMPO' => null,
            'TOTAL'       => 10000,
            'KETERANGAN'  => 'Uji poin',
            'MEMBER'      => $member->ID,
            'CART'        => [[
                'ID'              => $produk->ID,
                'NAMA'            => $produk->NAMA,
                'QUANTITY'        => 1,
                'TOTAL'           => 10000,
                'HARGA_ASLI'      => 10000,
                'POTONGAN_RUPIAH' => 0,
                'POTONGAN_PERSEN' => 0,
                'TIPE'            => 'Pieces',
            ]],
        ]);
    }

    private function poinSekarang(object $member): int
    {
        return (int) DB::table('ud84_member')->where('ID', $member->ID)->value('POINT');
    }

    public function test_the_rate_is_one_point_per_one_million(): void
    {
        $this->assertSame(1000000, (int) config('ud84.poin_per_rupiah'));
    }

    public function test_cash_just_under_a_million_earns_nothing(): void
    {
        $member = $this->seedMember();

        $this->jual($member, 999999);

        $this->assertSame(0, $this->poinSekarang($member));
    }

    public function test_cash_of_exactly_a_million_earns_one_point(): void
    {
        $member = $this->seedMember();

        $this->jual($member, 1000000);

        $this->assertSame(1, $this->poinSekarang($member));
    }

    public function test_points_accrue_in_multiples(): void
    {
        $member = $this->seedMember();

        // Two and a half million is two whole points; the remainder does not
        // round up.
        $this->jual($member, 2500000);

        $this->assertSame(2, $this->poinSekarang($member));
    }

    public function test_the_sale_records_the_points_it_granted(): void
    {
        $member = $this->seedMember();

        $this->jual($member, 2000000);

        $poin = DB::table('ud84_penjualan_rekap')->where('NAMA', $member->NAMA)->value('POIN');

        $this->assertSame(2, (int) $poin);
    }

    private function daftarPoin(): array
    {
        return $this->getJson('/api/UD84/Poin/Retrieve')
            ->assertStatus(200)->assertJson(['status' => 'success'])->json('data');
    }

    public function test_the_list_returns_every_member_with_a_balance(): void
    {
        $member = $this->seedMember(7);

        $data  = $this->daftarPoin();
        $found = collect($data['MEMBER'])->firstWhere('ID', $member->ID);

        $this->assertNotNull($found);
        $this->assertSame(7, (int) $found['POINT']);
        $this->assertSame($member->NAMA, $found['NAMA']);
        $this->assertSame('Singosari', $found['LOKASI']);
    }

    public function test_a_member_with_no_points_is_still_listed(): void
    {
        $member = $this->seedMember(0);

        // Excluding them would make giving anyone their first point impossible.
        $this->assertNotNull(collect($this->daftarPoin()['MEMBER'])->firstWhere('ID', $member->ID));
    }

    public function test_the_list_is_ordered_by_balance_highest_first(): void
    {
        $this->seedMember(3);
        $this->seedMember(9);

        $poin = collect($this->daftarPoin()['MEMBER'])->pluck('POINT')->map(fn ($p) => (int) $p)->all();

        $urut = $poin;
        rsort($urut);

        $this->assertSame($urut, $poin);
    }

    public function test_the_total_is_every_balance_added_up(): void
    {
        $data = $this->daftarPoin();

        $jumlah = collect($data['MEMBER'])->sum(fn ($m) => (int) $m['POINT']);

        $this->assertSame($jumlah, (int) $data['TOTAL']);
    }
}
