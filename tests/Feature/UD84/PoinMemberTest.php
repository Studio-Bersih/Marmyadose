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

    private function ubahPoin(array $payload = [])
    {
        return $this->postJson('/api/UD84/Poin/Adjust', array_merge([
            'JUMLAH' => 1,
            'ARAH'   => 'Tambah',
        ], $payload));
    }

    public function test_adding_raises_the_balance_and_returns_it(): void
    {
        $member = $this->seedMember(4);

        $this->ubahPoin(['ID' => $member->ID, 'JUMLAH' => 3, 'ARAH' => 'Tambah'])
            ->assertStatus(200)
            ->assertJson(['status' => 'success', 'data' => ['POINT' => 7]]);

        $this->assertSame(7, $this->poinSekarang($member));
    }

    public function test_subtracting_lowers_the_balance(): void
    {
        $member = $this->seedMember(10);

        $this->ubahPoin(['ID' => $member->ID, 'JUMLAH' => 4, 'ARAH' => 'Kurang'])
            ->assertStatus(200)
            ->assertJson(['status' => 'success', 'data' => ['POINT' => 6]]);

        $this->assertSame(6, $this->poinSekarang($member));
    }

    public function test_subtracting_the_whole_balance_is_allowed(): void
    {
        $member = $this->seedMember(5);

        $this->ubahPoin(['ID' => $member->ID, 'JUMLAH' => 5, 'ARAH' => 'Kurang'])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(0, $this->poinSekarang($member));
    }

    public function test_subtracting_more_than_the_balance_is_refused_and_names_it(): void
    {
        $member = $this->seedMember(2);

        $response = $this->ubahPoin(['ID' => $member->ID, 'JUMLAH' => 5, 'ARAH' => 'Kurang'])
            ->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertStringContainsString('2', $response->json('message'));
        $this->assertSame(2, $this->poinSekarang($member));
    }

    public function test_an_unknown_member_is_refused(): void
    {
        $this->ubahPoin(['ID' => 999999, 'JUMLAH' => 1, 'ARAH' => 'Tambah'])
            ->assertStatus(200)->assertJson(['status' => 'error']);
    }

    public function test_zero_and_negative_and_fractional_amounts_are_refused(): void
    {
        $member = $this->seedMember(3);

        foreach ([0, -2, 1.5, 'dua'] as $jumlah) {
            $this->ubahPoin(['ID' => $member->ID, 'JUMLAH' => $jumlah, 'ARAH' => 'Tambah'])
                ->assertStatus(200)->assertJson(['status' => 'error']);
        }

        $this->assertSame(3, $this->poinSekarang($member));
    }

    public function test_an_unrecognised_direction_is_refused(): void
    {
        $member = $this->seedMember(3);

        $this->ubahPoin(['ID' => $member->ID, 'JUMLAH' => 1, 'ARAH' => 'Ganti'])
            ->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(3, $this->poinSekarang($member));
    }

    public function test_adding_past_the_column_ceiling_is_refused_rather_than_crashing(): void
    {
        $member = $this->seedMember(32000);

        // POINT is a smallint; strict mode would turn the overflow into an
        // unexplained server error rather than a message anyone can act on.
        $this->ubahPoin(['ID' => $member->ID, 'JUMLAH' => 1000, 'ARAH' => 'Tambah'])
            ->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(32000, $this->poinSekarang($member));
    }
}
