<?php

namespace Tests\Feature\Kosada;

use DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * DatabaseTransactions — NOT RefreshDatabase. See TransferHarianTest.
 *
 * The Laporan follows the order of each marketing's ATM book once staff number
 * the loans, and keeps the old newest-first order for loans nobody has numbered.
 */
class UrutanAtmTest extends TestCase
{
    use DatabaseTransactions;

    /** A month the snapshot database has no real loans in, so the lists are exact. */
    private const AWAL  = '2030-02-01';
    private const AKHIR = '2030-02-28 23:59:59';

    /** @return array<string,int> NAMA => ID */
    private function seedKredit(array $loans): array
    {
        $ids = [];
        foreach($loans as $i => [$nama, $marketing]){
            $ids[$nama] = DB::table('kosada_kredit')->insertGetId([
                'NO_KREDIT'        => 'tesatm'.$i,
                'NAMA'             => $nama,
                'MARKETING'        => $marketing,
                'JUMLAH_PENGAJUAN' => 1000000,
                'JANGKA_WAKTU'     => 10,
                'LUNAS_BRP'        => 0,
                'STATUS'           => 'Yes',
                // Later index = later loan, so the old order is the reverse of $loans.
                'CREATED_AT'       => '2030-02-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).' 09:00:00',
            ]);
        }
        return $ids;
    }

    private function names(string $route = 'Report', string $marketing = 'TES-A'): array
    {
        $response = $this->postJson('/api/Kosada/'.$route, [
            'TANGGAL_AWAL'  => self::AWAL,
            'TANGGAL_AKHIR' => self::AKHIR,
            'MARKETING'     => $marketing,
            'per_page'      => 200,
        ])->assertStatus(200);

        return array_column($response->json('data'), 'NAMA');
    }

    private function setUrutan(int $id, $urutan)
    {
        return $this->postJson('/api/Kosada/Urutan-ATM', ['ID' => $id, 'URUTAN_ATM' => $urutan]);
    }

    public function test_unnumbered_report_keeps_the_old_newest_first_order(): void
    {
        $this->seedKredit([['ANI','TES-A'],['BUDI','TES-A'],['CICI','TES-A']]);

        $this->assertSame(['CICI','BUDI','ANI'], $this->names());
    }

    public function test_numbered_loans_follow_the_atm_book_and_unnumbered_follow_them(): void
    {
        $ids = $this->seedKredit([['ANI','TES-A'],['BUDI','TES-A'],['CICI','TES-A'],['DODI','TES-A']]);

        $this->setUrutan($ids['BUDI'], 1)->assertStatus(200);
        $this->setUrutan($ids['ANI'], 2)->assertStatus(200);
        $this->setUrutan($ids['DODI'], 3)->assertStatus(200);

        $this->assertSame(['BUDI','ANI','DODI','CICI'], $this->names());
        // The printed sheet is the one that goes to the ATM — it must match.
        $this->assertSame(['BUDI','ANI','DODI','CICI'], $this->names('Report/Print'));
    }

    public function test_report_returns_the_number(): void
    {
        $ids = $this->seedKredit([['ANI','TES-A']]);
        $this->setUrutan($ids['ANI'], 7);

        $row = $this->postJson('/api/Kosada/Report', [
            'TANGGAL_AWAL' => self::AWAL, 'TANGGAL_AKHIR' => self::AKHIR, 'MARKETING' => 'TES-A',
        ])->json('data.0');

        $this->assertSame(7, $row['URUTAN_ATM']);
    }

    /** Every book has its own #1, so an all-marketing report groups by marketing. */
    public function test_all_marketing_report_groups_each_book_together(): void
    {
        $ids = $this->seedKredit([['ANI','TES-B'],['BUDI','TES-A'],['CICI','TES-B'],['DODI','TES-A']]);

        $this->setUrutan($ids['ANI'], 1);
        $this->setUrutan($ids['BUDI'], 1);
        $this->setUrutan($ids['CICI'], 2);
        $this->setUrutan($ids['DODI'], 2);

        $names = array_values(array_intersect($this->names('Report', 'SEMUA'), ['ANI','BUDI','CICI','DODI']));
        $this->assertSame(['BUDI','DODI','ANI','CICI'], $names);
    }

    public function test_clearing_the_number_puts_the_loan_back_among_the_unnumbered(): void
    {
        $ids = $this->seedKredit([['ANI','TES-A'],['BUDI','TES-A']]);

        $this->setUrutan($ids['ANI'], 1);
        $this->assertSame(['ANI','BUDI'], $this->names());

        $this->setUrutan($ids['ANI'], null)->assertStatus(200);
        $this->assertNull(DB::table('kosada_kredit')->where('ID',$ids['ANI'])->value('URUTAN_ATM'));
        $this->assertSame(['BUDI','ANI'], $this->names());
    }

    /** Saving the number it already has is not "not found". */
    public function test_saving_the_same_number_twice_succeeds(): void
    {
        $ids = $this->seedKredit([['ANI','TES-A']]);

        $this->setUrutan($ids['ANI'], 3)->assertStatus(200);
        $this->setUrutan($ids['ANI'], 3)->assertStatus(200);
    }

    public function test_rejects_zero_negative_and_text(): void
    {
        $ids = $this->seedKredit([['ANI','TES-A']]);

        $this->setUrutan($ids['ANI'], 0)->assertStatus(422);
        $this->setUrutan($ids['ANI'], -4)->assertStatus(422);
        $this->setUrutan($ids['ANI'], 'abc')->assertStatus(422);

        $this->assertNull(DB::table('kosada_kredit')->where('ID',$ids['ANI'])->value('URUTAN_ATM'));
    }

    public function test_unknown_loan_is_404(): void
    {
        $this->setUrutan(999999999, 1)->assertStatus(404);
    }
}
