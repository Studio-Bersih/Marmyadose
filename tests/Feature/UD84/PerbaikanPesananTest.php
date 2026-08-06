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

    public function test_a_blank_date_range_is_refused_at_http_200(): void
    {
        // Not 400: db() throws on a non-2xx, retries, and reports a generic
        // connection error -- so the operator saw "Server Tidak Dapat Diakses"
        // instead of the message this endpoint actually wrote.
        $this->postJson('/api/UD84/Pesanan/Retrieve', ['start' => '', 'end' => ''])
            ->assertStatus(200)
            ->assertJson(['status' => 'error', 'message' => 'Harap masukkan tanggal awal dan akhir.']);
    }

    public function test_a_reversed_date_range_is_refused_at_http_200(): void
    {
        $this->postJson('/api/UD84/Pesanan/Retrieve', [
            'start' => '2026-08-31 00:00:00',
            'end'   => '2026-08-01 00:00:00',
        ])->assertStatus(200)->assertJson(['status' => 'error']);
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

    private function ubah(string $kode, array $payload = [])
    {
        return $this->postJson('/api/UD84/Pesanan/Update', array_merge([
            'KODE'     => $kode,
            'NAMA'     => 'Pelanggan Tes',
            'WHATSAPP' => '08123456789',
            'SALES'    => null,
            'CATATAN'  => 'Catatan awal',
            'ITEMS'    => [],
            'OPERATOR' => 'Tester',
            'ALASAN'   => '',
        ], $payload));
    }

    public function test_a_header_edit_updates_the_order_and_records_one_audit_row(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, [
            'NAMA'    => 'Pelanggan Baru',
            'CATATAN' => 'Antar sore',
            'ITEMS'   => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]],
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', [
            'KODE' => $kode, 'NAMA' => 'Pelanggan Baru', 'CATATAN' => 'Antar sore',
        ]);

        $log = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $kode)->get();

        $this->assertCount(1, $log);
        $this->assertSame('Edit Pesanan', $log[0]->AKSI);
        $this->assertSame('Tester', $log[0]->OPERATOR);
        $this->assertStringContainsString("Nama pelanggan: 'Pelanggan Tes' -> 'Pelanggan Baru'", $log[0]->CATATAN_SISTEM);
    }

    public function test_changing_a_quantity_leaves_the_line_created_date_alone(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, ['ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 5]]])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $line = DB::table('ud84_pesanan_detail')->where('KODE', $kode)->first();

        $this->assertSame(5, (int) $line->JUMLAH);
        // ud84_analisa_sales dates every line by this column. Rewriting the
        // line would move an old order's contribution into today.
        $this->assertStringStartsWith('2026-03-01', (string) $line->CREATED_AT);
    }

    public function test_an_item_can_be_added_and_another_removed(): void
    {
        $lama = $this->seedProduct();
        $baru = $this->seedProduct();
        $kode = $this->seedOrder([], [['KODE_ITEM' => $lama->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, ['ITEMS' => [['KODE_ITEM' => $baru->ID, 'JUMLAH' => 2]]])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $lama->ID]);
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $baru->ID, 'JUMLAH' => 2]);

        $catatan = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $kode)->value('CATATAN_SISTEM');

        $this->assertStringContainsString("Item '{$baru->NAMA}' ditambahkan (2)", $catatan);
        $this->assertStringContainsString("Item '{$lama->NAMA}' dihapus", $catatan);
    }

    public function test_the_salesperson_can_be_changed(): void
    {
        $produk  = $this->seedProduct();
        $salesId = $this->seedSalesperson();
        $kode    = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 1]]);

        $this->ubah($kode, [
            'SALES' => $salesId,
            'ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 1]],
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'SALES' => $salesId]);
    }

    public function test_the_before_and_after_snapshots_are_stored(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, ['ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 9]]]);

        $log = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $kode)->first();

        $sebelum = json_decode($log->SEBELUM, true);
        $sesudah = json_decode($log->SESUDAH, true);

        $this->assertSame(3, (int) $sebelum['detail'][0]['JUMLAH']);
        $this->assertSame(9, (int) $sesudah['detail'][0]['JUMLAH']);
    }

    public function test_a_duplicate_product_line_on_the_order_refuses_the_edit(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [
            ['KODE_ITEM' => $produk->ID, 'JUMLAH' => 2],
            ['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3],
        ]);

        $this->ubah($kode, ['ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 5]]])
            ->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(2, DB::table('ud84_pesanan_detail')->where('KODE', $kode)->count());
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 2]);
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]);
        $this->assertSame(0, DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $kode)->count());
    }

    public function test_an_unknown_order_is_refused(): void
    {
        $this->ubah('tidak-ada')->assertStatus(200)->assertJson(['status' => 'error']);
    }

    /**
     * The early guard reads VALID before DB::beginTransaction() and cannot
     * see a verification that lands after that read. A DB::listen hook fires
     * the instant that guard's SELECT returns -- while $rekap in the
     * controller still holds the pre-verification snapshot -- and flips the
     * row to Verified right then, simulating a second operator pressing
     * Validasi in that exact window. The early guard therefore necessarily
     * passes here; only the whereNull('VALID') predicate on the update
     * itself can catch this, which is what this test is proving.
     */
    public function test_an_order_verified_mid_request_is_refused_by_the_write_guard(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $sudahDipicu = false;

        DB::listen(function ($query) use ($kode, &$sudahDipicu) {
            if ($sudahDipicu) {
                return;
            }

            if (str_contains($query->sql, 'select * from `ud84_pesanan_rekap`')
                && in_array($kode, $query->bindings, true)) {
                $sudahDipicu = true;

                DB::table('ud84_pesanan_rekap')->where('KODE', $kode)->update(['VALID' => 'Verified']);
            }
        });

        $this->ubah($kode, [
            'NAMA'  => 'Diubah Diam-diam',
            'ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 9]],
        ])->assertStatus(200)->assertJson([
            'status'  => 'error',
            'message' => 'Pesanan yang sudah diverifikasi tidak bisa diubah.',
        ]);

        $this->assertTrue($sudahDipicu, 'The mid-request verification never fired -- this test proved nothing.');

        // Had the write actually run, NAMA would be 'Diubah Diam-diam' and
        // JUMLAH would be 9. Both stayed put, and no audit row was written.
        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'NAMA' => 'Pelanggan Tes', 'VALID' => 'Verified']);
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_a_verified_order_cannot_be_edited(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder(['VALID' => 'Verified'], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, [
            'NAMA'  => 'Diubah Diam-diam',
            'ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 9]],
        ])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'NAMA' => 'Pelanggan Tes']);
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'JUMLAH' => 3]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_a_blank_customer_name_is_refused(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, [
            'NAMA'  => '   ',
            'ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]],
        ])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'NAMA' => 'Pelanggan Tes']);
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_a_blank_whatsapp_is_refused(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, [
            'WHATSAPP' => '',
            'ITEMS'    => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]],
        ])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'WHATSAPP' => '08123456789']);
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_an_order_cannot_be_emptied(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, ['ITEMS' => []])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_an_unknown_product_rolls_the_whole_edit_back(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, [
            'NAMA'  => 'Pelanggan Baru',
            'ITEMS' => [
                ['KODE_ITEM' => $produk->ID, 'JUMLAH' => 5],
                ['KODE_ITEM' => 999999, 'JUMLAH' => 1],
            ],
        ])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'NAMA' => 'Pelanggan Tes']);
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'JUMLAH' => 3]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_a_zero_quantity_is_refused(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, ['ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 0]]])
            ->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_the_same_product_twice_is_refused(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, ['ITEMS' => [
            ['KODE_ITEM' => $produk->ID, 'JUMLAH' => 2],
            ['KODE_ITEM' => $produk->ID, 'JUMLAH' => 4],
        ]])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertSame(1, DB::table('ud84_pesanan_detail')->where('KODE', $kode)->count());
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_reassigning_to_a_deactivated_salesperson_is_refused(): void
    {
        $produk  = $this->seedProduct();
        $nonAktif = $this->seedSalesperson('Nonaktif');
        $kode    = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 1]]);

        $this->ubah($kode, [
            'SALES' => $nonAktif,
            'ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 1]],
        ])->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'SALES' => null]);
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 1]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_an_order_already_naming_a_deactivated_salesperson_can_still_be_edited(): void
    {
        $produk   = $this->seedProduct();
        $nonAktif = $this->seedSalesperson('Nonaktif');
        $kode     = $this->seedOrder(['SALES' => $nonAktif], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 1]]);

        $this->ubah($kode, [
            'NAMA'  => 'Pelanggan Baru',
            'SALES' => $nonAktif,
            'ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 1]],
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'SALES' => $nonAktif, 'NAMA' => 'Pelanggan Baru']);
    }

    public function test_an_edit_that_changes_nothing_is_refused_and_writes_no_audit_row(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->ubah($kode, ['ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]])
            ->assertStatus(200)->assertJson(['status' => 'error']);

        // The payload is identical to the seeded state, so a NAMA/WHATSAPP/JUMLAH
        // assertion here would pass whether the guard fired or the write path ran
        // anyway -- the written values would equal the seeded ones either way.
        // UPDATED_AT is null only until the success path stamps it, so it is what
        // a wrongly-executed write cannot fake.
        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'UPDATED_AT' => null]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_an_unresolvable_line_can_be_removed(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [
            ['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3],
            ['KODE_ITEM' => 999999, 'JUMLAH' => 1],
        ]);

        // The gone product is simply absent from the payload, so it never has
        // to resolve -- this is the only way out of such an order.
        $this->ubah($kode, ['ITEMS' => [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => 999999]);
    }

    public function test_deleting_an_order_records_what_was_deleted(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->postJson('/api/UD84/Pesanan/Delete', ['ID' => $kode, 'OPERATOR' => 'Tester'])
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('ud84_pesanan_rekap', ['KODE' => $kode]);
        $this->assertDatabaseMissing('ud84_pesanan_detail', ['KODE' => $kode]);

        $log = DB::table('ud84_transaksi_log')->where('UNIQUE_TRANSAKSI', $kode)->first();

        $this->assertSame('Hapus Pesanan', $log->AKSI);
        $this->assertSame('Tester', $log->OPERATOR);

        // Decoded rather than string-matched: MySQL returns smallint columns
        // as strings under some PDO settings, so "JUMLAH":3 and "JUMLAH":"3"
        // are both possible and both correct.
        $sebelum = json_decode($log->SEBELUM, true);

        $this->assertSame(3, (int) $sebelum['detail'][0]['JUMLAH']);
        $this->assertSame($kode, $sebelum['rekap']['KODE']);
    }

    public function test_a_verified_order_cannot_be_deleted(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder(['VALID' => 'Verified'], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $this->postJson('/api/UD84/Pesanan/Delete', ['ID' => $kode, 'OPERATOR' => 'Tester'])
            ->assertStatus(200)->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode]);
    }

    /** Same race, same mechanism, for the delete path -- see the update-path test above. */
    public function test_deleting_an_order_verified_mid_request_is_refused_by_the_write_guard(): void
    {
        $produk = $this->seedProduct();
        $kode   = $this->seedOrder([], [['KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]]);

        $sudahDipicu = false;

        DB::listen(function ($query) use ($kode, &$sudahDipicu) {
            if ($sudahDipicu) {
                return;
            }

            if (str_contains($query->sql, 'select * from `ud84_pesanan_rekap`')
                && in_array($kode, $query->bindings, true)) {
                $sudahDipicu = true;

                DB::table('ud84_pesanan_rekap')->where('KODE', $kode)->update(['VALID' => 'Verified']);
            }
        });

        $this->postJson('/api/UD84/Pesanan/Delete', ['ID' => $kode, 'OPERATOR' => 'Tester'])
            ->assertStatus(200)->assertJson([
                'status'  => 'error',
                'message' => 'Pesanan yang sudah diverifikasi tidak bisa dihapus.',
            ]);

        $this->assertTrue($sudahDipicu, 'The mid-request verification never fired -- this test proved nothing.');

        // Had the delete actually run, both rows and the audit insert made
        // before the rekap delete would be gone. The rollback undoes all
        // three, not just the last write.
        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'VALID' => 'Verified']);
        $this->assertDatabaseHas('ud84_pesanan_detail', ['KODE' => $kode, 'KODE_ITEM' => $produk->ID, 'JUMLAH' => 3]);
        $this->assertDatabaseMissing('ud84_transaksi_log', ['UNIQUE_TRANSAKSI' => $kode]);
    }

    public function test_deleting_an_unknown_order_is_refused(): void
    {
        $this->postJson('/api/UD84/Pesanan/Delete', ['ID' => 'tidak-ada'])
            ->assertStatus(200)->assertJson(['status' => 'error']);
    }

    public function test_verifying_reports_verification_not_deletion(): void
    {
        $kode = $this->seedOrder();

        $this->postJson('/api/UD84/Pesanan/Validate-Order', ['ID' => $kode])
            ->assertStatus(200)
            ->assertJson(['status' => 'success', 'message' => 'Pesanan berhasil diverifikasi.']);

        $this->assertDatabaseHas('ud84_pesanan_rekap', ['KODE' => $kode, 'VALID' => 'Verified']);
    }

    public function test_verifying_twice_is_refused(): void
    {
        $kode = $this->seedOrder(['VALID' => 'Verified']);

        $this->postJson('/api/UD84/Pesanan/Validate-Order', ['ID' => $kode])
            ->assertStatus(200)->assertJson(['status' => 'error']);
    }

    public function test_verifying_an_unknown_order_is_refused(): void
    {
        $this->postJson('/api/UD84/Pesanan/Validate-Order', ['ID' => 'tidak-ada'])
            ->assertStatus(200)->assertJson(['status' => 'error']);
    }
}
