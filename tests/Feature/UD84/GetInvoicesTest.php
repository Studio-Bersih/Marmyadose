<?php

namespace Tests\Feature\UD84;

use DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * DatabaseTransactions — NOT RefreshDatabase. RefreshDatabase would run
 * migrate:fresh and drop every ud84_* table; none are covered by migrations,
 * so they would not come back.
 */
class GetInvoicesTest extends TestCase
{
    use DatabaseTransactions;

    private function seedInvoice(array $rekap = [], array $details = []): string
    {
        $unique = 'test'.uniqid();

        DB::table('ud84_penjualan_rekap')->insert(array_merge([
            'UNIQUE'     => $unique,
            'NAMA'       => 'UMUM',
            'CASH'       => 0,
            'KEMBALIAN'  => 0,
            'DP'         => 0,
            'POTONGAN'   => 0,
            'TOTAL'      => 0,
            'MEMBER'     => 'UMUM',
            'CREATED_AT' => '2026-08-05 10:00:00',
        ], $rekap));

        foreach ($details as $detail) {
            DB::table('ud84_penjualan_detail')->insert(array_merge([
                'UNIQUE'          => $unique,
                'KODE'            => 1,
                'NAMA'            => 'BARANG TES',
                'SATUAN'          => null,
                'JUMLAH'          => 1,
                'HARGA_ASLI'      => 0,
                'HARGA_TERJUAL'   => 0,
                'POTONGAN_PERSEN' => 0,
                'POTONGAN_RUPIAH' => 0,
                'CREATED_AT'      => '2026-08-05 10:00:00',
            ], $detail));
        }

        return $unique;
    }

    private function fetch(string $unique): array
    {
        return $this->getJson('/api/UD84/Get-Invoices/'.$unique)->json('data');
    }

    /**
     * Mirrors real row 64ca60eb59cb1: 100 units at 8.000 = 800.000.
     * The old code printed Harga 800.000 and Jumlah 80.000.000.
     */
    public function test_line_total_is_not_multiplied_by_quantity_twice(): void
    {
        $unique = $this->seedInvoice(
            ['TOTAL' => 800000],
            [['JUMLAH' => 100, 'HARGA_ASLI' => 8000, 'HARGA_TERJUAL' => 800000]]
        );

        $line = $this->fetch($unique)['data'][0];

        $this->assertSame(8000, $line['HARGA']);
        $this->assertSame(800000, $line['JUMLAH']);
        $this->assertSame($line['JUMLAH'], $line['HARGA'] * $line['QUANTITY']);
    }

    /** Both discount columns are per-unit rupiah values. */
    public function test_unit_price_subtracts_both_discount_columns(): void
    {
        $unique = $this->seedInvoice(
            ['TOTAL' => 1010000],
            [[
                'JUMLAH' => 2, 'HARGA_ASLI' => 518000, 'HARGA_TERJUAL' => 1010000,
                'POTONGAN_PERSEN' => 10000, 'POTONGAN_RUPIAH' => 3000,
            ]]
        );

        $line = $this->fetch($unique)['data'][0];

        $this->assertSame(505000, $line['HARGA']);
        $this->assertSame(1010000, $line['JUMLAH']);
    }

    public function test_satuan_is_returned_when_present(): void
    {
        $unique = $this->seedInvoice([], [['SATUAN' => 'Set']]);

        $this->assertSame('Set', $this->fetch($unique)['data'][0]['SATUAN']);
    }

    public function test_satuan_is_null_for_legacy_rows(): void
    {
        $unique = $this->seedInvoice([], [['SATUAN' => null]]);

        $this->assertNull($this->fetch($unique)['data'][0]['SATUAN']);
    }

    public function test_ringkasan_without_potongan(): void
    {
        $unique = $this->seedInvoice(
            ['TOTAL' => 100000, 'POTONGAN' => 0],
            [['JUMLAH' => 1, 'HARGA_ASLI' => 100000, 'HARGA_TERJUAL' => 100000]]
        );

        $ringkasan = $this->fetch($unique)['ringkasan'];

        $this->assertSame(100000, $ringkasan['TOTAL_BARANG']);
        $this->assertSame(0, $ringkasan['POTONGAN']);
        $this->assertSame(100000, $ringkasan['TOTAL_TAGIHAN']);
    }

    public function test_ringkasan_with_potongan_reports_net_total(): void
    {
        $unique = $this->seedInvoice(
            ['TOTAL' => 1752000, 'POTONGAN' => 50000],
            [['JUMLAH' => 1, 'HARGA_ASLI' => 1802000, 'HARGA_TERJUAL' => 1802000]]
        );

        $ringkasan = $this->fetch($unique)['ringkasan'];

        $this->assertSame(1802000, $ringkasan['TOTAL_BARANG']);
        $this->assertSame(50000, $ringkasan['POTONGAN']);
        $this->assertSame(1752000, $ringkasan['TOTAL_TAGIHAN']);
    }

    public function test_sisa_tagihan_when_underpaid(): void
    {
        $unique = $this->seedInvoice(
            ['TOTAL' => 1752000, 'CASH' => 1600000, 'DP' => 100000]
        );

        $ringkasan = $this->fetch($unique)['ringkasan'];

        $this->assertSame(52000, $ringkasan['SISA']);
        $this->assertSame(0, $ringkasan['KEMBALIAN']);
    }

    public function test_kembalian_when_overpaid(): void
    {
        $unique = $this->seedInvoice(['TOTAL' => 100000, 'CASH' => 150000]);

        $ringkasan = $this->fetch($unique)['ringkasan'];

        $this->assertSame(50000, $ringkasan['KEMBALIAN']);
        $this->assertSame(0, $ringkasan['SISA']);
    }

    /**
     * Real row 27: CASH 0, DP 17500, TOTAL 17500, stored KEMBALIAN -17500.
     * Fully paid via DP; the stored column ignores DP and must not be used.
     */
    public function test_dp_counts_as_payment_and_stored_kembalian_is_ignored(): void
    {
        $unique = $this->seedInvoice(
            ['TOTAL' => 17500, 'CASH' => 0, 'DP' => 17500, 'KEMBALIAN' => -17500]
        );

        $ringkasan = $this->fetch($unique)['ringkasan'];

        $this->assertSame(0, $ringkasan['SISA']);
        $this->assertSame(0, $ringkasan['KEMBALIAN']);
    }

    public function test_unknown_invoice_returns_error_instead_of_crashing(): void
    {
        $response = $this->getJson('/api/UD84/Get-Invoices/tidak-ada-nota');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'error']);
        $this->assertNull($response->json('data'));
    }
}
