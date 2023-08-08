<?php

namespace App\Http\Controllers\UD84;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Penjualan extends Controller
{

    public function postPenjualan(Request $request){
        $uniqueID = uniqid();

        $namaMember = $request->input('MEMBER');

        if($namaMember != 'UMUM'){
            $dataMember = DB::table('ud84_member')->where('ID',$namaMember)->first();
            $namaMember = empty($dataMember->NAMA) ? 'UMUM' : $dataMember->NAMA;
        }

        DB::table('ud84_penjualan_rekap')->insert([
            "UNIQUE"            => $uniqueID,
            "NAMA"              => $namaMember,
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

                $item = DB::table('ud84_master_produk')->where('NAMA',$data['NAMA'])->first();
                DB::table('ud84_master_produk')->where('NAMA',$data['NAMA'])->update([
                    "STOK"  => $item->STOK - $data['QUANTITY']
                ]);
            }
        DB::commit();

        return response()->json([
            'status'    => 'success',
            'message'   => 'Data tersimpan!'
        ],200);
    }
}
