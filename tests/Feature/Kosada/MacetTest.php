<?php

namespace Tests\Feature\Kosada;

use DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * DatabaseTransactions — NOT RefreshDatabase. See TransferHarianTest.
 */
class MacetTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN_EMAIL    = 'admin-tes-macet@kosada.test';
    private const STAFF_EMAIL    = 'staff-tes-macet@kosada.test';
    private const PASSWORD       = 'rahasia-tes';
    private const NAMA           = 'NASABAH TES MACET';

    private int $kreditID;
    private int $macetID;

    protected function setUp(): void
    {
        parent::setUp();

        foreach([self::ADMIN_EMAIL => 'Administrator', self::STAFF_EMAIL => 'Staff'] as $email => $privilege){
            DB::table('users')->insert([
                'name'      => $privilege.' Tes',
                'email'     => $email,
                'password'  => Hash::make(self::PASSWORD),
                'groups'    => 'Kosada',
                'privilege' => $privilege,
                'STATUS'    => 'Aktif',
            ]);
        }

        $this->kreditID = DB::table('kosada_kredit')->insertGetId([
            'NO_KREDIT'        => 'tesmacet001',
            'NAMA'             => self::NAMA,
            'ALAMAT'           => 'ALAMAT TES',
            'MARKETING'        => 'TES',
            'JUMLAH_PENGAJUAN' => 5000000,
            'JANGKA_WAKTU'     => 10,
            'LUNAS_BRP'        => 0,
            'STATUS'           => 'Yes',
            'CREATED_AT'       => '2025-03-04 09:00:00',
            'UPDATED_AT'       => '2025-03-04 09:00:00',
        ]);

        $this->macetID = DB::table('kosada_kredit_macet')->insertGetId([
            'KREDIT_ID'     => $this->kreditID,
            'NO_KREDIT'     => 'tesmacet001',
            'ALASAN_MACET'  => 'alasan awal',
            'STATUS'        => 'Macet',
            'TANGGAL_MACET' => '2026-01-10',
        ]);
    }

    private function admin(string $email = self::ADMIN_EMAIL): array
    {
        return ['ADMIN_EMAIL' => $email, 'ADMIN_PASSWORD' => self::PASSWORD];
    }

    private function row(): array
    {
        $rows = $this->getJson('/api/Kosada/Data-Macet?status=SEMUA&nama='.urlencode(self::NAMA))->json('data');
        $this->assertCount(1, $rows);
        return $rows[0];
    }

    /** The first request: when the loan was taken out, beside the macet data. */
    public function test_list_carries_the_loan_date(): void
    {
        $row = $this->row();

        $this->assertSame('04 Maret 2025', $row['TANGGAL_PINJAMAN']);
        $this->assertSame('2026-01-10', $row['TANGGAL_MACET_ISO']);
    }

    public function test_print_carries_the_loan_date_too(): void
    {
        $rows = $this->getJson('/api/Kosada/Data-Macet/Print?status=SEMUA&nama='.urlencode(self::NAMA))->json('data');

        $this->assertSame('04 Maret 2025', $rows[0]['TANGGAL_PINJAMAN']);
    }

    public function test_administrator_can_edit_reason_and_date(): void
    {
        $this->postJson('/api/Kosada/Ubah-Macet', [
            'ID'            => $this->macetID,
            'ALASAN_MACET'  => 'alasan baru',
            'TANGGAL_MACET' => '2026-02-01',
        ] + $this->admin())->assertStatus(200)->assertJson(['status' => 'success']);

        $row = $this->row();
        $this->assertSame('alasan baru', $row['ALASAN_MACET']);
        $this->assertSame('2026-02-01', $row['TANGGAL_MACET_ISO']);
    }

    public function test_edit_refuses_a_future_date(): void
    {
        $this->postJson('/api/Kosada/Ubah-Macet', [
            'ID'            => $this->macetID,
            'ALASAN_MACET'  => 'alasan baru',
            'TANGGAL_MACET' => now()->addDay()->toDateString(),
        ] + $this->admin())->assertStatus(422);

        $this->assertSame('alasan awal', $this->row()['ALASAN_MACET']);
    }

    public function test_staff_cannot_edit(): void
    {
        $this->postJson('/api/Kosada/Ubah-Macet', [
            'ID'            => $this->macetID,
            'ALASAN_MACET'  => 'alasan baru',
            'TANGGAL_MACET' => '2026-02-01',
        ] + $this->admin(self::STAFF_EMAIL))->assertStatus(403);

        $this->assertSame('alasan awal', $this->row()['ALASAN_MACET']);
    }

    /** Deleting the case must leave the loan itself alone. */
    public function test_administrator_can_delete_and_the_loan_survives(): void
    {
        $this->postJson('/api/Kosada/Hapus-Macet', ['ID' => $this->macetID] + $this->admin())
            ->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertFalse(DB::table('kosada_kredit_macet')->where('ID',$this->macetID)->exists());
        $this->assertTrue(DB::table('kosada_kredit')->where('ID',$this->kreditID)->exists());
    }

    /** A deleted case can be registered again — the UNIQUE key is free. */
    public function test_a_deleted_case_can_be_registered_again(): void
    {
        $this->postJson('/api/Kosada/Hapus-Macet', ['ID' => $this->macetID] + $this->admin())->assertStatus(200);

        $this->postJson('/api/Kosada/Tambah-Macet', [
            'KREDIT_ID'    => $this->kreditID,
            'ALASAN_MACET' => 'macet lagi',
        ])->assertStatus(200);

        $this->assertSame('macet lagi', $this->row()['ALASAN_MACET']);
    }

    public function test_staff_cannot_delete(): void
    {
        $this->postJson('/api/Kosada/Hapus-Macet', ['ID' => $this->macetID] + $this->admin(self::STAFF_EMAIL))
            ->assertStatus(403);

        $this->assertTrue(DB::table('kosada_kredit_macet')->where('ID',$this->macetID)->exists());
    }

    public function test_delete_without_password_is_refused(): void
    {
        $this->postJson('/api/Kosada/Hapus-Macet', ['ID' => $this->macetID])->assertStatus(401);

        $this->assertTrue(DB::table('kosada_kredit_macet')->where('ID',$this->macetID)->exists());
    }
}
