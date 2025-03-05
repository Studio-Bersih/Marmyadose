<?php

namespace App\Http\Controllers\UD84;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class Pesanan extends Controller
{
    public function postPesanan(Request $request) {
        DB::beginTransaction();

        try {
            $unique = uniqid();
            $nama = $request->input('NAMA');
            $whatsApp = $request->input('WHATSAPP');
            $sales = $request->input('SALES');
            $carts = $request->input('CARTS');
            $notes = $request->input('NOTES');

            if(empty($carts)) {
                return response()->json([
                    "status"    => "error",
                    "message"   => "Tidak ada item di keranjang"
                ],200);
            }

            DB::table('ud84_pesanan_rekap')->insert([
                "NAMA"      => $nama,
                "WHATSAPP"  => $whatsApp,
                "SALES"     => $sales,
                "KODE"      => $unique,
                "CATATAN"   => $notes,
            ]);

            $useCarts = [];
            for($i = 0; $i < count($carts); $i++) {
                $useCarts[] = [
                    "KODE"      => $unique,
                    "KODE_ITEM" => $carts[$i]['ID'],
                    "JUMLAH"    => $carts[$i]['QUANTITY'],
                ];
            }

            DB::table('ud84_pesanan_detail')->insert($useCarts);

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Pesanan tersimpan!',
            ],200);
        } catch (\Throwable $e) {
            DB::rollback();
            Log::info($e);
            return response()->json([
                'status'    => 'error',
                'message'   => 'Ada kesalahan pada server.',
            ], 200);
        }
    }
}
