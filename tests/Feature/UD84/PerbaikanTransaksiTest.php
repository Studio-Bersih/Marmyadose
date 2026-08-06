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

    /**
     * 114 of 409 real products carry a null or zero JUMLAH_PER_ITEM, including
     * more than half of every Dus and a quarter of every Set. A line sold in
     * one of those units cannot have its pieces computed, so the gate must
     * refuse it same as it refuses a missing KODE or SATUAN -- and the sale
     * must still be reachable through the header-only path, or it becomes
     * uncorrectable entirely. I-2.
     */
    public function test_a_line_needing_an_unknown_per_item_count_blocks_item_editing_but_allows_header_correction(): void
    {
        $produk = $this->seedProduct(['TIPE' => 'Dus', 'JUMLAH_PER_ITEM' => 0]);
        $unique = $this->seedSale(['NAMA' => 'ASLI', 'TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Dus',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $koreksi = $this->koreksiBlock($unique);

        $this->assertFalse($koreksi['DAPAT_UBAH_ITEM']);
        $this->assertStringContainsString($produk->NAMA, $koreksi['ALASAN']);

        $this->perbaiki($unique, ['NAMA' => 'DIPERBAIKI'])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'NAMA' => 'DIPERBAIKI']);
    }

    private function perbaiki(string $unique, array $payload = [])
    {
        return $this->postJson('/api/UD84/Daftar-Transaksi/Perbaiki', array_merge([
            'KODE'        => $unique,
            'NAMA'        => 'UMUM',
            'KETERANGAN'  => null,
            'JATUH_TEMPO' => null,
            'CASH'        => 0,
            'DP'          => 0,
            'POTONGAN'    => 0,
            'OPERATOR'    => 'Tester',
            'ALASAN'      => 'Salah input kasir',
        ], $payload));
    }

    public function test_a_header_correction_updates_the_sale_and_records_one_audit_row(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['NAMA' => 'BU EKA', 'KETERANGAN' => 'Antar sore'])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_penjualan_rekap', [
            'UNIQUE' => $unique, 'NAMA' => 'BU EKA', 'KETERANGAN' => 'Antar sore',
        ]);

        $log = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $unique)->get();

        $this->assertCount(1, $log);
        $this->assertSame('Perbaikan', $log[0]->AKSI);
        $this->assertSame('Tester', $log[0]->OPERATOR);
        $this->assertSame('Salah input kasir', $log[0]->ALASAN);
    }

    public function test_the_total_is_recomputed_from_the_stored_lines_and_the_new_potongan(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['TOTAL' => 100000, 'CASH' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['CASH' => 100000, 'POTONGAN' => 10000])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $rekap = DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->first();

        $this->assertSame(90000, (int) $rekap->TOTAL);
        // KEMBALIAN is recomputed the way postPenjualan writes it at sale time.
        $this->assertSame(10000, (int) $rekap->KEMBALIAN);
    }

    public function test_points_settle_the_difference_when_cash_is_corrected(): void
    {
        $memberId = $this->seedMember('BU EKA '.uniqid(), 3);
        $nama     = DB::table('ud84_member')->where('ID', $memberId)->value('NAMA');
        $produk   = $this->seedProduct();
        $unique   = $this->seedSale(['NAMA' => $nama, 'CASH' => 1000000, 'POIN' => 2, 'TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        // 1.500.000 cash earns 1 point; the sale already granted 2, so the
        // member's balance moves by the difference of -1, not to 1 and not by
        // the whole new figure.
        $this->perbaiki($unique, ['NAMA' => $nama, 'CASH' => 1500000])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(2, (int) DB::table('ud84_member')->where('ID', $memberId)->value('POINT'));
        $this->assertSame(1, (int) DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->value('POIN'));
    }

    public function test_points_move_between_members_when_the_customer_name_is_corrected(): void
    {
        $salahId = $this->seedMember('SALAH ORANG '.uniqid(), 5);
        $benarId = $this->seedMember('ORANG BENAR '.uniqid(), 1);
        $salah   = DB::table('ud84_member')->where('ID', $salahId)->value('NAMA');
        $benar   = DB::table('ud84_member')->where('ID', $benarId)->value('NAMA');
        $produk  = $this->seedProduct();
        $unique  = $this->seedSale(['NAMA' => $salah, 'CASH' => 1000000, 'POIN' => 2, 'TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['NAMA' => $benar, 'CASH' => 1000000])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        // The wrong member gives back the 2 the sale STORED as granted, which
        // no rate change can alter. The right member receives what it grants
        // today: 1.000.000 of cash is 1 point.
        $this->assertSame(3, (int) DB::table('ud84_member')->where('ID', $salahId)->value('POINT'));
        $this->assertSame(2, (int) DB::table('ud84_member')->where('ID', $benarId)->value('POINT'));
    }

    public function test_a_point_deduction_floors_at_zero_and_says_so(): void
    {
        $memberId = $this->seedMember('SALDO TIPIS '.uniqid(), 1);
        $nama     = DB::table('ud84_member')->where('ID', $memberId)->value('NAMA');
        $produk   = $this->seedProduct();
        $unique   = $this->seedSale(['NAMA' => $nama, 'CASH' => 2000000, 'POIN' => 4, 'TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['NAMA' => $nama, 'CASH' => 0])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(0, (int) DB::table('ud84_member')->where('ID', $memberId)->value('POINT'));

        $catatan = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $unique)->value('CATATAN_SISTEM');

        $this->assertStringContainsString('saldo tidak mencukupi', $catatan);
    }

    public function test_the_grant_is_inferred_from_cash_when_poin_predates_the_column(): void
    {
        $memberId = $this->seedMember('POIN LAMA '.uniqid(), 5);
        $nama     = DB::table('ud84_member')->where('ID', $memberId)->value('NAMA');
        $produk   = $this->seedProduct();
        $unique   = $this->seedSale(['NAMA' => $nama, 'CASH' => 2000000, 'POIN' => null, 'TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        // POIN is null, so what this sale granted is inferred from its stored
        // CASH -- under TODAY'S rate, not the one in force when it was rung
        // up: floor(2.000.000 / 1.000.000) = 2. Correcting CASH to 0 takes
        // those 2 back from the member's 5, landing on 3. That imprecision is
        // accepted and documented: a sale that never recorded its grant cannot
        // be reversed exactly.
        $this->perbaiki($unique, ['NAMA' => $nama, 'CASH' => 0])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(3, (int) DB::table('ud84_member')->where('ID', $memberId)->value('POINT'));
    }

    public function test_umum_never_gains_or_loses_points(): void
    {
        // A member literally named UMUM would be the only way this rule could
        // fail silently: without the special case, geserPoin would resolve
        // it like any other name and credit it.
        $umumId = $this->seedMember('UMUM', 7);
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['NAMA' => 'UMUM', 'CASH' => 0, 'POIN' => 0, 'TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['NAMA' => 'UMUM', 'CASH' => 2000000])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(7, (int) DB::table('ud84_member')->where('ID', $umumId)->value('POINT'));
        $this->assertSame(0, (int) DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->value('POIN'));
    }

    public function test_a_blank_customer_name_is_stored_as_umum(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['NAMA' => 'BU EKA', 'TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['NAMA' => '   '])->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'NAMA' => 'UMUM']);
    }

    public function test_the_before_and_after_snapshots_are_stored(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['NAMA' => 'SEBELUM', 'TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['NAMA' => 'SESUDAH']);

        $log     = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $unique)->first();
        $sebelum = json_decode($log->SEBELUM, true);
        $sesudah = json_decode($log->SESUDAH, true);

        $this->assertSame('SEBELUM', $sebelum['rekap']['NAMA']);
        $this->assertSame('SESUDAH', $sesudah['rekap']['NAMA']);
        $this->assertCount(1, $sebelum['detail']);
    }

    /** The line ID of a sale's single stored line. */
    private function lineId(string $unique): int
    {
        return (int) DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->value('ID');
    }

    public function test_raising_a_quantity_takes_more_stock_and_recomputes_the_total(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 5, 'HARGA_ASLI' => 50000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]])->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(97, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertSame(250000, (int) DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->value('TOTAL'));
        $this->assertSame(250000, (int) DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->value('HARGA_TERJUAL'));
    }

    public function test_lowering_a_quantity_returns_stock(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 1, 'HARGA_ASLI' => 50000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]]);

        $this->assertSame(101, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
    }

    public function test_a_set_line_moves_stock_by_the_per_item_multiplier(): void
    {
        $produk = $this->seedProduct(['STOK' => 100, 'TIPE' => 'Set', 'JUMLAH_PER_ITEM' => 6]);
        $unique = $this->seedSale(['TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Set',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        // 2 Set -> 3 Set is one more Set, which is six more pieces.
        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Set',
            'JUMLAH' => 3, 'HARGA_ASLI' => 50000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]]);

        $this->assertSame(94, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
    }

    public function test_stock_nets_across_two_lines_of_the_same_product(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 100000], [
            ['KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs', 'JUMLAH' => 4, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 40000],
            ['KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs', 'JUMLAH' => 6, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 60000],
        ]);

        $ids = DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->orderBy('ID')->pluck('ID')->all();

        // One line up by 3, the other down by 3: ten pieces before, ten after,
        // so stock must not move at all and no log row is written.
        $this->perbaiki($unique, ['ITEMS' => [
            ['ID' => $ids[0], 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs', 'JUMLAH' => 7, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0],
            ['ID' => $ids[1], 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs', 'JUMLAH' => 3, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0],
        ]])->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertSame(0, DB::table('ud84_logs')->where('KODE_ITEM', $produk->ID)->where('ASAL', 'Perbaikan Transaksi')->count());

        // Netting correctly is not enough if it netted by collapsing the two
        // rows into one -- both original rows must still exist, each carrying
        // its own new quantity.
        $this->assertSame(2, DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->count());
        $this->assertSame(7, (int) DB::table('ud84_penjualan_detail')->where('ID', $ids[0])->value('JUMLAH'));
        $this->assertSame(3, (int) DB::table('ud84_penjualan_detail')->where('ID', $ids[1])->value('JUMLAH'));
    }

    public function test_a_product_moving_between_lines_returns_one_and_takes_the_other(): void
    {
        $lama = $this->seedProduct(['STOK' => 100]);
        $baru = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 40000], [[
            'KODE' => $lama->ID, 'NAMA' => $lama->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 4, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 40000,
        ]]);

        $id = $this->lineId($unique);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $id, 'KODE_ITEM' => $baru->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 4, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]]);

        $this->assertSame(104, (int) DB::table('ud84_master_produk')->where('ID', $lama->ID)->value('STOK'));
        $this->assertSame(96, (int) DB::table('ud84_master_produk')->where('ID', $baru->ID)->value('STOK'));

        // The swap is an edit of the same row, not a delete-plus-add: one
        // detail row for this sale, still on its original ID, now pointing
        // at the new product.
        $this->assertSame(1, DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->count());
        $this->assertSame((int) $baru->ID, (int) DB::table('ud84_penjualan_detail')->where('ID', $id)->value('KODE'));
    }

    public function test_a_line_can_be_added_and_another_removed(): void
    {
        $lama = $this->seedProduct(['STOK' => 100]);
        $baru = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 40000], [[
            'KODE' => $lama->ID, 'NAMA' => $lama->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 4, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 40000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'KODE_ITEM' => $baru->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 5000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]])->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('ud84_penjualan_detail', ['UNIQUE' => $unique, 'KODE' => $lama->ID]);
        $this->assertDatabaseHas('ud84_penjualan_detail', ['UNIQUE' => $unique, 'KODE' => $baru->ID, 'JUMLAH' => 2]);
        $this->assertSame(104, (int) DB::table('ud84_master_produk')->where('ID', $lama->ID)->value('STOK'));
        $this->assertSame(98, (int) DB::table('ud84_master_produk')->where('ID', $baru->ID)->value('STOK'));
        $this->assertSame(10000, (int) DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->value('TOTAL'));
    }

    public function test_a_surviving_line_keeps_its_created_date(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 3, 'HARGA_ASLI' => 50000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]]);

        $line = DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->first();

        $this->assertStringStartsWith('2026-03-01', (string) $line->CREATED_AT);
    }

    /**
     * A line a correction adds must report under the SALE's month, not the
     * month the correction happened to be made in -- Report::omsetDetail,
     * singleItemReport and singleItem all bucket by d.CREATED_AT, so a line
     * dated today would put its quantity in today's product reports while its
     * money stayed in the sale's own month's revenue. seedSale dates its sale
     * 2026-03-01, which is a different month from whenever this test runs, so
     * a line dated `now()` is observable. I-1.
     */
    public function test_a_line_added_by_a_correction_inherits_the_sales_date_not_todays(): void
    {
        $lama   = $this->seedProduct(['STOK' => 100]);
        $baru   = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $lama->ID, 'NAMA' => $lama->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [
            ['ID' => $this->lineId($unique), 'KODE_ITEM' => $lama->ID, 'SATUAN' => 'Pcs', 'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0],
            ['KODE_ITEM' => $baru->ID, 'SATUAN' => 'Pcs', 'JUMLAH' => 1, 'HARGA_ASLI' => 5000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0],
        ]])->assertStatus(200)->assertJson(['status' => 'success']);

        $ditambahkan = DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->where('KODE', $baru->ID)->first();

        $this->assertStringStartsWith('2026-03-01', (string) $ditambahkan->CREATED_AT);
    }

    public function test_stock_may_go_negative_and_is_reported(): void
    {
        $produk = $this->seedProduct(['STOK' => 1]);
        $unique = $this->seedSale(['TOTAL' => 10000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 1, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 10000,
        ]]);

        $response = $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 6, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]])->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(-4, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertContains($produk->NAMA, $response->json('data.STOK_MINUS'));
    }

    public function test_a_stock_adjustment_writes_a_log_row_without_touching_the_original(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        DB::table('ud84_logs')->insert([
            'KODE_ITEM' => $produk->ID, 'NAMA_ITEM' => $produk->NAMA, 'ASAL' => 'Retail',
            'MASUK' => 0, 'KELUAR' => 2, 'STOK_FINAL' => 100, 'CREATED_AT' => '2026-03-01 09:00:00',
        ]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 5, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]]);

        $koreksi = DB::table('ud84_logs')->where('KODE_ITEM', $produk->ID)->where('ASAL', 'Perbaikan Transaksi')->first();

        $this->assertSame(3, (int) $koreksi->KELUAR);
        $this->assertSame(0, (int) $koreksi->MASUK);
        $this->assertSame(97, (int) $koreksi->STOK_FINAL);

        // The sale's original movement is history and stays untouched.
        $asli = DB::table('ud84_logs')->where('KODE_ITEM', $produk->ID)->where('ASAL', 'Retail')->first();

        $this->assertSame(2, (int) $asli->KELUAR);
        $this->assertSame(100, (int) $asli->STOK_FINAL);
    }

    public function test_the_change_list_names_the_stock_movement(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 5, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]]);

        $catatan = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $unique)->value('CATATAN_SISTEM');

        $this->assertStringContainsString("Stok '{$produk->NAMA}' dikurangi 3 pcs (100 -> 97)", $catatan);
    }

    /**
     * Two payload entries referencing the same stored row would otherwise both
     * pass the ownership check, both apply their own stock delta on top of the
     * same starting line, and collapse to a single surviving row -- inventory
     * short with no line accounting for it, and TOTAL disagreeing with its own
     * detail. Review round 1 finding 1.
     */
    public function test_a_duplicate_line_id_is_refused(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 40000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 4, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 40000,
        ]]);

        $id = $this->lineId($unique);

        $response = $this->perbaiki($unique, ['ITEMS' => [
            ['ID' => $id, 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs', 'JUMLAH' => 4, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0],
            ['ID' => $id, 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs', 'JUMLAH' => 4, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0],
        ]]);

        $response->assertStatus(200)->assertJson(['status' => 'error']);
        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertSame(1, DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->count());
        $this->assertSame(4, (int) DB::table('ud84_penjualan_detail')->where('ID', $id)->value('JUMLAH'));
        $this->assertSame(40000, (int) DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->value('TOTAL'));
    }

    /**
     * A line the request never mentions is still implicitly deleted if it is
     * not among the submitted IDs, and deleting it still needs to know how
     * many pieces it represents so stock can be returned. syaratUbahItem's
     * gate checks KODE and SATUAN but not JUMLAH_PER_ITEM, so a product that
     * lost its per-item count slips past the gate; only the netting step
     * would notice. Review round 1 finding 3.
     */
    public function test_an_untouched_old_line_whose_product_lacks_a_per_item_count_blocks_the_correction(): void
    {
        $bad  = $this->seedProduct(['STOK' => 100, 'TIPE' => 'Set', 'JUMLAH_PER_ITEM' => 0]);
        $good = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 150000], [
            ['KODE' => $bad->ID, 'NAMA' => $bad->NAMA, 'SATUAN' => 'Set', 'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000],
            ['KODE' => $good->ID, 'NAMA' => $good->NAMA, 'SATUAN' => 'Pcs', 'JUMLAH' => 5, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 50000],
        ]);

        $goodId = DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->where('KODE', $good->ID)->value('ID');

        // Only the good line is resubmitted; the bad line would be implicitly
        // removed, which still requires knowing how many pieces it represents.
        $response = $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $goodId, 'KODE_ITEM' => $good->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 6, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]]);

        $response->assertStatus(200)->assertJson(['status' => 'error']);
        $this->assertStringContainsString($bad->NAMA, $response->json('message'));
        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $bad->ID)->value('STOK'));
        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $good->ID)->value('STOK'));
        $this->assertSame(5, (int) DB::table('ud84_penjualan_detail')->where('ID', $goodId)->value('JUMLAH'));
        $this->assertSame(2, DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->count());
    }

    public function test_an_unknown_sale_is_refused(): void
    {
        $this->perbaiki('tidak-ada')->assertStatus(200)->assertJson(['status' => 'error']);
    }

    public function test_a_cancelled_sale_cannot_be_corrected(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['STATUS' => 'Dibatalkan', 'NAMA' => 'ASLI', 'TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['NAMA' => 'DIUBAH'])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'NAMA' => 'ASLI']);
        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $unique]);
    }

    public function test_a_blank_reason_is_refused(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['NAMA' => 'ASLI', 'TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['NAMA' => 'DIUBAH', 'ALASAN' => '   '])
            ->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'NAMA' => 'ASLI']);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $unique]);
    }

    public function test_items_are_refused_on_a_sale_that_does_not_qualify(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => null,
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 5, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_penjualan_detail', ['UNIQUE' => $unique, 'JUMLAH' => 2]);
        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $unique]);
    }

    public function test_a_header_correction_still_works_on_a_sale_that_does_not_qualify(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['NAMA' => 'ASLI', 'TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => null,
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['NAMA' => 'DIPERBAIKI'])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'NAMA' => 'DIPERBAIKI']);
    }

    public function test_an_empty_item_list_is_refused(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => []])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_penjualan_detail', ['UNIQUE' => $unique, 'JUMLAH' => 2]);
    }

    public function test_a_line_id_from_another_sale_is_refused(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $lain   = $this->seedSale(['TOTAL' => 10000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 1, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 10000,
        ]]);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($lain), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 9, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_penjualan_detail', ['UNIQUE' => $lain, 'JUMLAH' => 1]);
        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
    }

    public function test_an_unknown_product_is_refused(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'KODE_ITEM' => 999999, 'SATUAN' => 'Pcs', 'JUMLAH' => 1,
            'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $unique]);
    }

    public function test_a_unit_the_product_does_not_use_is_refused(): void
    {
        $produk = $this->seedProduct(['STOK' => 100, 'TIPE' => 'Set']);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Dus',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
    }

    public function test_a_zero_quantity_is_refused(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 0, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_penjualan_detail', ['UNIQUE' => $unique, 'JUMLAH' => 2]);
    }

    public function test_negative_money_is_refused(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['NAMA' => 'ASLI', 'TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['CASH' => -5000])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $unique]);
    }

    public function test_a_potongan_larger_than_the_goods_is_refused(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['POTONGAN' => 25000])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'TOTAL' => 20000]);
    }

    public function test_an_item_discount_larger_than_its_price_is_refused(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 8000, 'POTONGAN_RUPIAH' => 5000,
        ]]])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
    }

    /**
     * Every other item test in this file passes both discount columns as
     * zero, so the arithmetic HARGA_TERJUAL = (HARGA_ASLI - POTONGAN_PERSEN -
     * POTONGAN_RUPIAH) * JUMLAH is never exercised with real discounts -- and
     * that exact formula is what an earlier sub-project's headline defect got
     * wrong. Promoted from the deferred list.
     */
    public function test_item_discounts_are_subtracted_before_multiplying_by_quantity(): void
    {
        $produk = $this->seedProduct(['STOK' => 100]);
        $unique = $this->seedSale(['TOTAL' => 100000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 50000, 'HARGA_TERJUAL' => 100000,
        ]]);

        // (50.000 - 5.000 - 2.000) * 3 = 129.000
        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
            'JUMLAH' => 3, 'HARGA_ASLI' => 50000, 'POTONGAN_PERSEN' => 5000, 'POTONGAN_RUPIAH' => 2000,
        ]]])->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(129000, (int) DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->value('HARGA_TERJUAL'));
        $this->assertSame(129000, (int) DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->value('TOTAL'));
    }

    public function test_a_product_with_no_per_item_count_cannot_be_sold_by_the_set(): void
    {
        $produk = $this->seedProduct(['STOK' => 100, 'TIPE' => 'Set', 'JUMLAH_PER_ITEM' => 0]);
        $unique = $this->seedSale(['TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        // Pieces would be a guess, and a guess here is wrong by the very
        // multiplier that is missing.
        $this->perbaiki($unique, ['ITEMS' => [[
            'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Set',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
        ]]])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $unique]);
    }

    /**
     * Stock, points, lines and header must go in together or not at all. Every
     * guard runs before the transaction opens, so the only way to prove the
     * rollback is to make a write inside it fail: this listener throws the
     * moment the audit-log insert runs, which is the LAST write in
     * perbaikiTransaksi -- by that point stock, the detail rows, the member's
     * points and the rekap header have all already been written, so all four
     * assertions below carry real weight rather than passing vacuously
     * because their writes were never attempted.
     */
    public function test_a_failure_midway_rolls_back_stock_points_lines_and_header(): void
    {
        $memberId = $this->seedMember('MEMBER ROLLBACK '.uniqid(), 5);
        $nama     = DB::table('ud84_member')->where('ID', $memberId)->value('NAMA');
        $produk   = $this->seedProduct(['STOK' => 100]);
        $unique   = $this->seedSale(['NAMA' => $nama, 'CASH' => 1000000, 'POIN' => 2, 'TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into `ud84_transaksi_log`')) {
                throw new \RuntimeException('kegagalan buatan');
            }
        });

        $this->perbaiki($unique, [
            'NAMA'  => $nama,
            'CASH'  => 1500000,
            'ITEMS' => [[
                'ID' => $this->lineId($unique), 'KODE_ITEM' => $produk->ID, 'SATUAN' => 'Pcs',
                'JUMLAH' => 9, 'HARGA_ASLI' => 10000, 'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0,
            ]],
        ])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(100, (int) DB::table('ud84_master_produk')->where('ID', $produk->ID)->value('STOK'));
        $this->assertSame(5, (int) DB::table('ud84_member')->where('ID', $memberId)->value('POINT'));
        $this->assertDatabaseHas('ud84_penjualan_detail', ['UNIQUE' => $unique, 'JUMLAH' => 2]);
        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'CASH' => 1000000, 'POIN' => 2]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $unique]);
    }

    public function test_a_correction_that_changes_nothing_is_refused(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['NAMA' => 'UMUM', 'TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        // Every field already holds these values.
        $this->perbaiki($unique)->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $unique]);
        $this->assertDatabaseHas('ud84_penjualan_rekap', ['UNIQUE' => $unique, 'UPDATED_AT' => null]);
    }

    public function test_a_corrected_sale_reports_itself_as_corrected_to_the_nota(): void
    {
        $produk = $this->seedProduct();
        $unique = $this->seedSale(['NAMA' => 'ASLI', 'TOTAL' => 20000], [[
            'KODE' => $produk->ID, 'NAMA' => $produk->NAMA, 'SATUAN' => 'Pcs',
            'JUMLAH' => 2, 'HARGA_ASLI' => 10000, 'HARGA_TERJUAL' => 20000,
        ]]);

        // Seeded directly, not via the cancel endpoint -- that would also flip
        // STATUS and pull in behaviour this test is not about. The point is
        // narrower: ud84_transaksi_log holds rows from four different actions,
        // and getInvoices must key on AKSI = 'Perbaikan' specifically, not on
        // "this sale has any audit row at all". Batal is the most pointed
        // choice, since that is the one whose sale would otherwise print the
        // wrong banner.
        DB::table('ud84_transaksi_log')->insert([
            'UNIQUE_TRANSAKSI' => $unique,
            'AKSI'             => 'Batal',
            'OPERATOR'         => 'Tester',
            'ALASAN'           => 'Uji AKSI lain tidak ikut terhitung',
            'CREATED_AT'       => now(),
        ]);

        $sebelum = $this->getJson('/api/UD84/Get-Invoices/'.$unique)->assertStatus(200)->json('data');

        $this->assertFalse($sebelum['dikoreksi']);
        $this->assertNull($sebelum['dikoreksi_pada']);

        $this->perbaiki($unique, ['NAMA' => 'DIPERBAIKI'])->assertJson(['status' => 'success']);

        $sesudah = $this->getJson('/api/UD84/Get-Invoices/'.$unique)->assertStatus(200)->json('data');

        $this->assertTrue($sesudah['dikoreksi']);
        $this->assertNotNull($sesudah['dikoreksi_pada']);
    }
}
