<?php

namespace Tests\Feature\Kosada;

use DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * DatabaseTransactions — NOT RefreshDatabase. RefreshDatabase would run
 * migrate:fresh and drop every kosada_* table; none are covered by migrations,
 * so they would not come back.
 */
class TransferHarianTest extends TestCase
{
    use DatabaseTransactions;

    /** A date the snapshot database has no real rows on, so counts are exact. */
    private const TANGGAL = '2030-01-15';

    private function seedTransfers(int $jumlah): void
    {
        $rows = [];

        for($i = 1; $i <= $jumlah; $i++){
            $rows[] = [
                'TANGGAL_TRANSFER' => self::TANGGAL,
                'NAMA'             => 'NASABAH TES '.$i,
                'INSTANSI'         => 'INSTANSI TES',
                'JENIS'            => 'Kasbon',
                'NOMINAL'          => 100000 * $i,
                'KETERANGAN'       => null,
                'CREATED_AT'       => self::TANGGAL.' 08:00:00',
                'UPDATED_AT'       => self::TANGGAL.' 08:00:00',
            ];
        }

        DB::table('kosada_transfer_harian')->insert($rows);
    }

    /**
     * The complaint this test exists for: a day with more than 50 transfers
     * showed only the first 50 on the page, while the footer kept reporting the
     * day's real total. The recap is one day's sheet — every line of it has to
     * come back, the same way Transfer@printTransferHarian returns the whole
     * day to the printed sheet.
     */
    public function test_returns_every_transfer_of_the_day_past_fifty(): void
    {
        $this->seedTransfers(63);

        $response = $this->getJson('/api/Kosada/Transfer-Harian?tanggal='.self::TANGGAL);

        $response->assertStatus(200);
        $this->assertCount(63, $response->json('data'));
        $this->assertSame(63, $response->json('meta.total'));
    }

    /**
     * The footer sums the whole day, so the rows it sits under must be the
     * whole day too — a truncated list under a full-day total is the shape the
     * bug took.
     */
    public function test_total_nominal_matches_the_rows_returned(): void
    {
        $this->seedTransfers(63);

        $response = $this->getJson('/api/Kosada/Transfer-Harian?tanggal='.self::TANGGAL);
        $rows     = $response->json('data');

        $this->assertSame(
            array_sum(array_column($rows,'NOMINAL')),
            $response->json('meta.total_nominal')
        );
    }
}
