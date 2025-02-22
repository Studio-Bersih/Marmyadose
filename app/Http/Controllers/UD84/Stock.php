<?php

namespace App\Http\Controllers\UD84;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Log;

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

    public function stocksAdmin(Request $request){
        $tipe = $request->input('tipe');
        $catatan = $request->input('catatan');
        $penanggungJawab = $request->input('penanggungJawabAfkir');
        $carts = $request->input('cart');
        $key = uniqid();

        DB::beginTransaction();

        try {
            // If "Item Keluar" and responsible persons exist, log them
            if ($tipe === "Item Keluar" && !empty($penanggungJawab)) {
                $setAfkir = array_map(fn($pj) => [
                    "KEY"     => $key,
                    "USER"    => $pj['ID'],
                    "NOMINAL" => $pj['NOMINAL']
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

            // Prepare stock update and logging
            $stocksUpdate = [];

            foreach ($carts as $cart) {
                // Update stock
                DB::table('ud84_master_produk')
                    ->where('ID', $cart['ID'])
                    ->update([
                        'STOK' => DB::raw("STOK " . ($tipe === "Item Keluar" ? "-" : "+") . " {$cart['INPUT_STOK']}")
                    ]);

                // Prepare log data
                $stocksUpdate[] = [
                    "STOK"  => $cart['INPUT_STOK'],
                    "TIPE"  => $tipe,
                    "KEY"   => $key
                ];
            }

            // Batch insert for logging
            if (!empty($stocksUpdate)) {
                DB::table('ud84_logistics_detail')->insert($stocksUpdate);
            }

            DB::commit();

            return response()->json([
                "status"  => "success",
                "message" => "Data tersimpan"
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"  => "error",
                "message" => "Terjadi kesalahan.",
                "error"   => $e->getMessage()
            ], 500);
        }
    }

    
}
