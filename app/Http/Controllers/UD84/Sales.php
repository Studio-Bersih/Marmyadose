<?php

namespace App\Http\Controllers\UD84;

use DB;
use Log;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Sales extends Controller
{
    private const STATUSES = ['Aktif', 'Nonaktif'];

    /**
     * ud84_pesanan_rekap.SALES and ud84_member.CREATED_BY both store a
     * salesperson's ID. Deleting a referenced salesperson blanks their name on
     * every order they took and every member they registered, so the delete
     * endpoint refuses in that case and the UI offers deactivation instead.
     * Counting here lets the list tell the operator which is which up front.
     */
    private function referenceCount(int $id): array
    {
        return [
            'PESANAN' => DB::table('ud84_pesanan_rekap')->where('SALES', $id)->count(),
            'MEMBER'  => DB::table('ud84_member')->where('CREATED_BY', $id)->count(),
        ];
    }

    private function nameTaken(string $nama, ?int $exceptId = null): bool
    {
        $query = DB::table('ud84_sales')->whereRaw('LOWER(TRIM(NAMA)) = ?', [mb_strtolower($nama)]);

        if ($exceptId !== null) {
            $query->where('ID', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function getSales()
    {
        try {
            $rows = DB::table('ud84_sales')->orderBy('NAMA')->get();

            $list = [];
            foreach ($rows as $row) {
                $refs = $this->referenceCount((int) $row->ID);

                $list[] = [
                    'ID'            => (int) $row->ID,
                    'NAMA'          => $row->NAMA,
                    'STATUS'        => $row->STATUS,
                    'PESANAN'       => $refs['PESANAN'],
                    'MEMBER'        => $refs['MEMBER'],
                    'DAPAT_DIHAPUS' => $refs['PESANAN'] === 0 && $refs['MEMBER'] === 0,
                ];
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Loaded',
                'data'    => $list,
            ], 200);
        } catch (\Throwable $e) {
            Log::info($e);
            return response()->json([
                'status'  => 'error',
                'message' => 'Ada kesalahan pada server.',
            ], 200);
        }
    }

    public function postSales(Request $request)
    {
        try {
            $nama = trim((string) $request->input('NAMA'));

            if ($nama === '') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Nama sales tidak boleh kosong.',
                ], 200);
            }

            if ($this->nameTaken($nama)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Nama sales sudah terdaftar.',
                ], 200);
            }

            DB::table('ud84_sales')->insert([
                'NAMA'       => $nama,
                'STATUS'     => 'Aktif',
                'CREATED_AT' => now(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Sales berhasil ditambahkan.',
            ], 200);
        } catch (\Throwable $e) {
            Log::info($e);
            return response()->json([
                'status'  => 'error',
                'message' => 'Ada kesalahan pada server.',
            ], 200);
        }
    }

    public function updateSales(Request $request)
    {
        try {
            $id     = (int) $request->input('ID');
            $sales  = DB::table('ud84_sales')->where('ID', $id)->first();

            if (empty($sales)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sales tidak ditemukan.',
                ], 200);
            }

            $nama   = trim((string) $request->input('NAMA', $sales->NAMA));
            $status = (string) $request->input('STATUS', $sales->STATUS);

            if ($nama === '') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Nama sales tidak boleh kosong.',
                ], 200);
            }

            if (!in_array($status, self::STATUSES, true)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Status sales tidak valid.',
                ], 200);
            }

            if ($this->nameTaken($nama, $id)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Nama sales sudah terdaftar.',
                ], 200);
            }

            DB::table('ud84_sales')->where('ID', $id)->update([
                'NAMA'       => $nama,
                'STATUS'     => $status,
                'UPDATED_AT' => now(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Data sales berhasil diperbarui.',
            ], 200);
        } catch (\Throwable $e) {
            Log::info($e);
            return response()->json([
                'status'  => 'error',
                'message' => 'Ada kesalahan pada server.',
            ], 200);
        }
    }

    public function deleteSales(Request $request)
    {
        try {
            $id    = (int) $request->input('ID');
            $sales = DB::table('ud84_sales')->where('ID', $id)->first();

            if (empty($sales)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sales tidak ditemukan.',
                ], 200);
            }

            $refs = $this->referenceCount($id);

            if ($refs['PESANAN'] > 0 || $refs['MEMBER'] > 0) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sales ini sudah punya '.$refs['PESANAN'].' pesanan dan '.$refs['MEMBER'].' member. Nonaktifkan saja agar riwayatnya tetap utuh.',
                ], 200);
            }

            DB::table('ud84_sales')->where('ID', $id)->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Sales berhasil dihapus.',
            ], 200);
        } catch (\Throwable $e) {
            Log::info($e);
            return response()->json([
                'status'  => 'error',
                'message' => 'Ada kesalahan pada server.',
            ], 200);
        }
    }
}
