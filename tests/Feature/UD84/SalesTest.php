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
class SalesTest extends TestCase
{
    use DatabaseTransactions;

    private function seedSales(string $nama, string $status = 'Aktif'): int
    {
        return (int) DB::table('ud84_sales')->insertGetId([
            'NAMA'       => $nama,
            'STATUS'     => $status,
            'CREATED_AT' => '2026-08-06 10:00:00',
        ]);
    }

    private function list(): array
    {
        return $this->getJson('/api/UD84/Sales/Retrieve')->json('data');
    }

    private function find(array $list, int $id): ?array
    {
        foreach ($list as $row) {
            if ($row['ID'] === $id) {
                return $row;
            }
        }

        return null;
    }

    public function test_list_returns_status_and_reference_counts(): void
    {
        $id = $this->seedSales('Tes Sales '.uniqid());

        $row = $this->find($this->list(), $id);

        $this->assertNotNull($row);
        $this->assertSame('Aktif', $row['STATUS']);
        $this->assertSame(0, $row['PESANAN']);
        $this->assertSame(0, $row['MEMBER']);
        $this->assertTrue($row['DAPAT_DIHAPUS']);
    }

    public function test_insert_creates_an_active_salesperson(): void
    {
        $nama = 'Sales Baru '.uniqid();

        $this->postJson('/api/UD84/Sales/Insert', ['NAMA' => $nama])
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_sales', ['NAMA' => $nama, 'STATUS' => 'Aktif']);
    }

    public function test_insert_trims_surrounding_whitespace(): void
    {
        $nama = 'Sales Spasi '.uniqid();

        $this->postJson('/api/UD84/Sales/Insert', ['NAMA' => '   '.$nama.'   ']);

        $this->assertDatabaseHas('ud84_sales', ['NAMA' => $nama]);
    }

    public function test_insert_rejects_an_empty_name(): void
    {
        $before = DB::table('ud84_sales')->count();

        $this->postJson('/api/UD84/Sales/Insert', ['NAMA' => '   '])
            ->assertStatus(200)
            ->assertJson(['status' => 'error']);

        $this->assertSame($before, DB::table('ud84_sales')->count());
    }

    public function test_insert_rejects_a_duplicate_name_regardless_of_case(): void
    {
        $nama = 'Duplikat '.uniqid();
        $this->seedSales($nama);

        $this->postJson('/api/UD84/Sales/Insert', ['NAMA' => mb_strtoupper($nama)])
            ->assertStatus(200)
            ->assertJson(['status' => 'error']);

        $this->assertSame(1, DB::table('ud84_sales')->whereRaw('LOWER(NAMA) = ?', [mb_strtolower($nama)])->count());
    }

    public function test_update_renames_a_salesperson(): void
    {
        $id   = $this->seedSales('Nama Lama '.uniqid());
        $baru = 'Nama Baru '.uniqid();

        $this->postJson('/api/UD84/Sales/Update', ['ID' => $id, 'NAMA' => $baru])
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_sales', ['ID' => $id, 'NAMA' => $baru]);
    }

    public function test_update_deactivates_without_touching_the_name(): void
    {
        $nama = 'Sales Nonaktif '.uniqid();
        $id   = $this->seedSales($nama);

        $this->postJson('/api/UD84/Sales/Update', ['ID' => $id, 'STATUS' => 'Nonaktif'])
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_sales', ['ID' => $id, 'NAMA' => $nama, 'STATUS' => 'Nonaktif']);
    }

    public function test_update_rejects_an_invalid_status(): void
    {
        $id = $this->seedSales('Sales Status '.uniqid());

        $this->postJson('/api/UD84/Sales/Update', ['ID' => $id, 'STATUS' => 'Dipecat'])
            ->assertStatus(200)
            ->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_sales', ['ID' => $id, 'STATUS' => 'Aktif']);
    }

    public function test_update_allows_a_salesperson_to_keep_its_own_name(): void
    {
        $nama = 'Sales Tetap '.uniqid();
        $id   = $this->seedSales($nama);

        $this->postJson('/api/UD84/Sales/Update', ['ID' => $id, 'NAMA' => $nama, 'STATUS' => 'Nonaktif'])
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('ud84_sales', ['ID' => $id, 'STATUS' => 'Nonaktif']);
    }

    public function test_update_rejects_a_name_already_used_by_someone_else(): void
    {
        $taken = 'Sales Terpakai '.uniqid();
        $this->seedSales($taken);
        $id = $this->seedSales('Sales Lain '.uniqid());

        $this->postJson('/api/UD84/Sales/Update', ['ID' => $id, 'NAMA' => $taken])
            ->assertStatus(200)
            ->assertJson(['status' => 'error']);
    }

    public function test_update_rejects_an_unknown_salesperson(): void
    {
        $this->postJson('/api/UD84/Sales/Update', ['ID' => 999999, 'NAMA' => 'Hantu'])
            ->assertStatus(200)
            ->assertJson(['status' => 'error']);
    }

    public function test_delete_removes_an_unreferenced_salesperson(): void
    {
        $id = $this->seedSales('Sales Hapus '.uniqid());

        $this->postJson('/api/UD84/Sales/Delete', ['ID' => $id])
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('ud84_sales', ['ID' => $id]);
    }

    /** Deleting would blank this salesperson's name on the order they took. */
    public function test_delete_refuses_when_referenced_by_an_order(): void
    {
        $id = $this->seedSales('Sales Berpesanan '.uniqid());

        DB::table('ud84_pesanan_rekap')->insert([
            'NAMA'       => 'Pelanggan Tes',
            'WHATSAPP'   => '08123456789',
            'SALES'      => $id,
            'CATATAN'    => 'Tes',
            'KODE'       => 'test'.uniqid(),
            'CREATED_AT' => '2026-08-06 10:00:00',
        ]);

        $this->postJson('/api/UD84/Sales/Delete', ['ID' => $id])
            ->assertStatus(200)
            ->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_sales', ['ID' => $id]);
    }

    public function test_delete_refuses_when_referenced_by_a_member(): void
    {
        $id = $this->seedSales('Sales Bermember '.uniqid());

        DB::table('ud84_member')->insert([
            'UNIQUE'     => 'test'.uniqid(),
            'NAMA'       => 'Member Tes',
            'WHATSAPP'   => '08987654321',
            'CREATED_BY' => (string) $id,
            'CREATED_AT' => '2026-08-06 10:00:00',
        ]);

        $this->postJson('/api/UD84/Sales/Delete', ['ID' => $id])
            ->assertStatus(200)
            ->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('ud84_sales', ['ID' => $id]);
    }

    public function test_list_reports_a_referenced_salesperson_as_undeletable(): void
    {
        $id = $this->seedSales('Sales Terpakai Ref '.uniqid());

        DB::table('ud84_pesanan_rekap')->insert([
            'NAMA'       => 'Pelanggan Tes',
            'WHATSAPP'   => '08123456789',
            'SALES'      => $id,
            'KODE'       => 'test'.uniqid(),
            'CREATED_AT' => '2026-08-06 10:00:00',
        ]);

        $row = $this->find($this->list(), $id);

        $this->assertSame(1, $row['PESANAN']);
        $this->assertFalse($row['DAPAT_DIHAPUS']);
    }

    /**
     * getPesanan dereferenced the salesperson row without a null guard. An
     * order placed with "Tanpa Sales" has SALES null, so there is no row to
     * find — it emitted a PHP warning and printed a blank cell.
     */
    public function test_order_without_a_salesperson_lists_a_dash(): void
    {
        DB::table('ud84_pesanan_rekap')->insert([
            'NAMA'       => 'Pelanggan Tanpa Sales',
            'WHATSAPP'   => '08111111111',
            'SALES'      => null,
            'KODE'       => 'test'.uniqid(),
            'CREATED_AT' => '2026-08-06 10:00:00',
        ]);

        $data = $this->postJson('/api/UD84/Pesanan/Retrieve', [
            'start' => '2026-08-06 00:00:00',
            'end'   => '2026-08-06 23:59:59',
        ])->json('data');

        $found = null;
        foreach ($data as $row) {
            if ($row['NAMA'] === 'Pelanggan Tanpa Sales') {
                $found = $row;
            }
        }

        $this->assertNotNull($found, 'seeded order was not returned');
        $this->assertSame('-', $found['SALES']);
    }
}
