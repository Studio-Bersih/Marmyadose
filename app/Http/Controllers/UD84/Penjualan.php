<?php

namespace App\Http\Controllers\UD84;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Penjualan extends Controller
{
    public function postPenjualan(Request $request){
        $uniqueID = uniqid();
        DB::table('ud84_penjualan_rekap')->insert([
            "UNIQUE"            => $uniqueID,
            "DP"                => $request->input('DP'),
            "CASH"              => $request->input('CASH'),
            "JATUH_TEMPO"       => $request->input('JATUH_TEMPO'),
            "TOTAL"             => $request->input('TOTAL'),
            "KETERANGAN"        => $request->input('KETERANGAN'),
        ]);

        DB::beginTransaction();
        foreach($request->input('CART') as $data){
            DB::table('ud84_penjualan_detail')->insert([
                "UNIQUE"          => $uniqueID,
                "NAMA"            => $data['NAMA'],
                "JUMLAH"          => $data['QUANTITY'],
                "HARGA_ASLI"      => $data['HARGA_ASLI'],
                "HARGA_TERJUAL"   => $data['TOTAL'],
                "POTONGAN_PERSEN" => $data['POTONGAN_PERSEN'],
                "POTONGAN_RUPIAH" => $data['POTONGAN_RUPIAH'],
            ]);
        }
        DB::commit();

        return response()->json([
            'status'    => 'success',
            'message'   => 'Data tersimpan!'
        ],200);
    }
}
