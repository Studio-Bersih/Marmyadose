<?php

namespace Tests\Feature\UD84;

use DB;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * A STATUS column is worthless unless every consumer honours it. These tests
 * exist so a cancelled sale cannot quietly go on counting as revenue.
 *
 * DatabaseTransactions — NOT RefreshDatabase, which would drop every ud84_*
 * table; none are covered by migrations, so they would not come back.
 */
class LaporanKecualiBatalTest extends TestCase
{
    use DatabaseTransactions;

    private string $namaProduk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->namaProduk = 'PRODUK LAPORAN '.uniqid();
    }

    /** A sale dated inside the current month, so month-scoped reports see it. */
    private function seedSale(string $status, int $total, int $jumlah = 2, int $terjual = 0): string
    {
        $unique = 'lap'.uniqid();
        $when   = Carbon::now()->startOfMonth()->addDays(1)->format('Y-m-d H:i:s');

        DB::table('ud84_penjualan_rekap')->insert([
            'UNIQUE' => $unique, 'STATUS' => $status, 'NAMA' => 'UMUM',
            'CASH' => $total, 'KEMBALIAN' => 0, 'DP' => 0, 'POTONGAN' => 0,
            'TOTAL' => $total, 'MEMBER' => 'UMUM', 'POIN' => 0, 'CREATED_AT' => $when,
        ]);

        DB::table('ud84_penjualan_detail')->insert([
            'UNIQUE' => $unique, 'KODE' => null, 'NAMA' => $this->namaProduk, 'SATUAN' => 'Pcs',
            'JUMLAH' => $jumlah, 'HARGA_ASLI' => $total, 'HARGA_TERJUAL' => $terjual ?: $total,
            'POTONGAN_PERSEN' => 0, 'POTONGAN_RUPIAH' => 0, 'CREATED_AT' => $when,
        ]);

        return $unique;
    }

    public function test_monthly_omset_excludes_cancelled_sales(): void
    {
        // MySQL SUM() comes back as a string, so compare as integers.
        $before = (int) $this->getJson('/api/UD84/Charts')->json('data.OMSET');

        $this->seedSale('Aktif', 100000);
        $afterActive = (int) $this->getJson('/api/UD84/Charts')->json('data.OMSET');
        $this->assertSame($before + 100000, $afterActive);

        $this->seedSale('Dibatalkan', 500000);
        $afterCancelled = (int) $this->getJson('/api/UD84/Charts')->json('data.OMSET');
        $this->assertSame($afterActive, $afterCancelled, 'a cancelled sale still counted toward monthly omset');
    }

    public function test_item_breakdown_excludes_cancelled_sales(): void
    {
        $this->seedSale('Aktif', 100000, 2, 100000);
        $this->seedSale('Dibatalkan', 900000, 9, 900000);

        $omset = $this->getJson('/api/UD84/Omset')->json('data.OMSET');

        $row = null;
        foreach ($omset as $entry) {
            if ($entry['NAMA'] === $this->namaProduk) {
                $row = $entry;
            }
        }

        $this->assertNotNull($row, 'seeded product missing from the breakdown');
        $this->assertSame(2, (int) $row['JUMLAH'], 'cancelled quantity leaked into the item breakdown');
        $this->assertSame(100000, (int) $row['TOTAL'], 'cancelled revenue leaked into the item breakdown');
    }

    public function test_transaction_search_hides_cancelled_sales_by_default(): void
    {
        $aktif  = $this->seedSale('Aktif', 100000);
        $batal  = $this->seedSale('Dibatalkan', 500000);
        $range  = $this->range();

        $ids = array_column($this->postJson('/api/UD84/Daftar-Transaksi/Search', $range)->json('data.data'), 'ID');

        $this->assertContains($aktif, $ids);
        $this->assertNotContains($batal, $ids);
    }

    public function test_transaction_search_can_show_cancelled_sales_on_request(): void
    {
        $aktif = $this->seedSale('Aktif', 100000);
        $batal = $this->seedSale('Dibatalkan', 500000);

        $rows = $this->postJson('/api/UD84/Daftar-Transaksi/Search', $this->range(true))->json('data.data');
        $ids  = array_column($rows, 'ID');

        $this->assertContains($aktif, $ids);
        $this->assertContains($batal, $ids);

        foreach ($rows as $row) {
            if ($row['ID'] === $batal) {
                $this->assertSame('Dibatalkan', $row['STATUS']);
            }
        }
    }

    /** The row may be visible; the footer must still not count it. */
    public function test_totals_exclude_cancelled_sales_even_when_they_are_shown(): void
    {
        $hidden = $this->postJson('/api/UD84/Daftar-Transaksi/Search', $this->range())->json('data.TRANSAKSI');

        $this->seedSale('Dibatalkan', 750000);

        $shown = $this->postJson('/api/UD84/Daftar-Transaksi/Search', $this->range(true))->json('data.TRANSAKSI');

        $this->assertSame($hidden, $shown, 'a cancelled sale was added into the list total');
    }

    public function test_single_item_analysis_excludes_cancelled_sales(): void
    {
        $produkId = DB::table('ud84_master_produk')->insertGetId([
            'NAMA' => $this->namaProduk, 'STOK' => 100, 'TIPE' => 'Pieces',
            'STATUS_JUAL' => 'Katalog dan Penjualan', 'HARGA_PABRIK' => 1000,
            'HARGA_JUAL' => 2000, 'JUMLAH_PER_ITEM' => 1, 'HARGA_PER_ITEM' => 2000,
        ]);

        $this->seedSale('Aktif', 100000, 2, 100000);
        $this->seedSale('Dibatalkan', 900000, 9, 900000);

        $data = $this->postJson('/api/UD84/Reports/Single-Item', [
            'ID'     => $produkId,
            'START'  => Carbon::now()->startOfMonth()->format('Y-m-d H:i:s'),
            'FINISH' => Carbon::now()->endOfMonth()->format('Y-m-d H:i:s'),
        ])->json('data');

        $this->assertSame(2, (int) $data['TOTAL_PIECES'], 'cancelled pieces leaked into single-item analysis');
        $this->assertSame(100000, (int) $data['TOTAL_KOTOR'], 'cancelled revenue leaked into single-item analysis');
    }

    public function test_per_item_month_report_excludes_cancelled_sales(): void
    {
        $this->seedSale('Aktif', 100000, 2, 100000);
        $this->seedSale('Dibatalkan', 900000, 9, 900000);

        $rows = $this->getJson('/api/UD84/Omset/Single/'.rawurlencode($this->namaProduk))->json('data');

        $this->assertCount(1, $rows, 'cancelled sale appeared in the per-item month report');
        $this->assertSame(2, (int) $rows[0]['JUMLAH']);
    }

    private function range(bool $tampilkanBatal = false): array
    {
        return [
            'start'            => Carbon::now()->startOfMonth()->format('Y-m-d H:i:s'),
            'end'              => Carbon::now()->endOfMonth()->format('Y-m-d H:i:s'),
            'TAMPILKAN_BATAL'  => $tampilkanBatal,
        ];
    }
}
