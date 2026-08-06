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

        // 1.500.000 cash earns 3 points; the sale already granted 2, so the
        // member's balance moves by 1, not by 3.
        $this->perbaiki($unique, ['NAMA' => $nama, 'CASH' => 1500000])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertSame(4, (int) DB::table('ud84_member')->where('ID', $memberId)->value('POINT'));
        $this->assertSame(3, (int) DB::table('ud84_penjualan_rekap')->where('UNIQUE', $unique)->value('POIN'));
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

        $this->assertSame(3, (int) DB::table('ud84_member')->where('ID', $salahId)->value('POINT'));
        $this->assertSame(3, (int) DB::table('ud84_member')->where('ID', $benarId)->value('POINT'));
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
}
