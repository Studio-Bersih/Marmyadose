<?php

namespace App\Http\Controllers\UD84;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Log;
use DB;

class Stock extends Controller
{
    public function getUser(){
        return response()->json([
            "status"    => "success",
            "message"   => "OK",
            "data"      => [
                [
                    "ID" => 1,
                    "NAMA" => "Agus"
                ],
                [
                    "ID" => 2,
                    "NAMA" => "Budi"
                ],
                [
                    "ID" => 3,
                    "NAMA" => "Caca"
                ]
            ]
        ],200);
    }

    public function dashboard(Request $request) {
        try {
            $keyWords  = $request->input('searchBar');
            $startDate = $request->input('start');
            $endDate   = $request->input('end');
            $tipe      = $request->input('tipe'); // 'Item Masuk' or 'Item Keluar'

            // Validasi tanggal tidak kosong
            if (!$startDate || !$endDate) {
                return response()->json([
                    "status"  => "error",
                    "message" => "Harap masukkan tanggal awal dan akhir."
                ], 400);
            }

            // Pastikan endDate tidak lebih awal dari startDate
            if (strtotime($endDate) < strtotime($startDate)) {
                return response()->json([
                    "status"  => "error",
                    "message" => "Tanggal akhir tidak boleh lebih awal dari tanggal mulai."
                ], 400);
            }

            // Query Data dari Database
            $query = DB::table('ud84_logistics')->where('TIPE', $tipe)->where('CREATED_AT', '>=', $startDate)->where('CREATED_AT', '<=', $endDate);

            // Filter berdasarkan ID jika ada kata kunci
            if (!empty($keyWords)) {
                $query->where('ID', $keyWords);
            }

            // Ambil data & format hasil
            $data = $query->get(['ID', 'KETERANGAN', 'CREATED_AT'])
                ->map(fn ($item) => [
                    "ID"           => $item->ID,
                    "NO_TRANSAKSI" => ($tipe === "Item Masuk" ? "LOG/IM/" : "LOG/IK/") . $item->ID,
                    "KETERANGAN"   => $item->KETERANGAN,
                    "CREATED_AT"   => $item->CREATED_AT
                ]);

            return response()->json([
                "status"  => "success",
                "message" => "Berhasil dimuat.",
                "data"    => $data
            ], 200);

        } catch (\Exception $e) {
            Log::info($e);
            return response()->json([
                "status"  => "error",
                "message" => "Terjadi kesalahan, silakan coba lagi."
            ], 500);
        }
    }
    
    public function stocksAdmin(Request $request){
        $tipe = $request->input('tipe');
        $catatan = $request->input('catatan');
        $penanggungJawab = $request->input('penanggungJawabAfkir');
        $carts = $request->input('cart');
        $key = uniqid();

        DB::beginTransaction();

        try {
            // Insert responsible persons if "Item Keluar"
            if ($tipe === "Item Keluar" && !empty($penanggungJawab)) {
                $setAfkir = array_map(fn($pj) => [
                    "KEY"     => $key,
                    "NAMA"    => $pj['NAMA'],
                    "USER"    => $pj['ID'],
                    "NOMINAL" => preg_replace('/\D/', '', $pj['NOMINAL'])
                ], $penanggungJawab);

                DB::table('ud84_afkir_responsible')->insert($setAfkir);
            }

            // Insert main logistics entry
            DB::table('ud84_logistics')->insert([
                "TIPE"       => $tipe,
                "KEY"        => $key,
                "KETERANGAN" => $catatan,
                "CREATED_AT" => now()
            ]);

            // Prepare stock update, logging, and transaction logs
            $stocksUpdate = [];
            $logEntries = [];

            foreach ($carts as $cart) {
                // Update stock
                DB::table('ud84_master_produk')
                    ->where('ID', $cart['ID'])
                    ->update([
                        'STOK' => DB::raw("STOK " . ($tipe === "Item Keluar" ? "-" : "+") . " {$cart['INPUT_STOK']}")
                    ]);

                // Prepare log data
                $stocksUpdate[] = [
                    "KODE" => $cart['ID'],
                    "NAMA" => $cart['NAMA'],
                    "STOK"  => $cart['INPUT_STOK'],
                    "TIPE"  => $tipe,
                    "KEY"   => $key
                ];

                // Prepare transaction logs for history (no updates, only inserts)
                $logEntries[] = [
                    "KODE_ITEM" => $cart['ID'],
                    "NAMA_ITEM" => $cart['NAMA'],
                    "ASAL"      => $tipe,
                    "MASUK"     => $tipe === "Item Masuk" ? $cart['INPUT_STOK'] : 0,
                    "KELUAR"    => $tipe === "Item Keluar" ? $cart['INPUT_STOK'] : 0,
                    "CREATED_AT"=> now()
                ];
            }

            // Batch insert for stock logs
            if (!empty($stocksUpdate)) {
                DB::table('ud84_logistics_detail')->insert($stocksUpdate);
            }

            // Batch insert for transaction logs (history)
            if (!empty($logEntries)) {
                DB::table('ud84_logs')->insert($logEntries);
            }

            DB::commit();

            return response()->json([
                "status"  => "success",
                "message" => "Data tersimpan"
            ], 200);
        } catch (\Exception $e) {
            Log::info($e);
            DB::rollBack();
            return response()->json([
                "status"  => "error",
                "message" => "Terjadi kesalahan.",
                "error"   => $e->getMessage()
            ], 500);
        }
    }


    
}
