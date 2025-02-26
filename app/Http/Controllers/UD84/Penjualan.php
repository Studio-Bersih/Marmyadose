<?php

namespace App\Http\Controllers\UD84;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Log;

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
            "JATUH_TEMPO"       => $request->input('JATUH_TEMPO') ?? NULL,
            "TOTAL"             => $request->input('TOTAL'),
            "KETERANGAN"        => $request->input('KETERANGAN'),
        ]);

        DB::beginTransaction();
            foreach($request->input('CART') as $data){
                DB::table('ud84_penjualan_detail')->insert([
                    "UNIQUE"          => $uniqueID,
                    "ID"              => $data['ID'],
                    "NAMA"            => $data['NAMA'],
                    "JUMLAH"          => $data['QUANTITY'],
                    "HARGA_ASLI"      => $data['HARGA_ASLI'],
                    "HARGA_TERJUAL"   => $data['TOTAL'],
                    "POTONGAN_PERSEN" => $data['POTONGAN_PERSEN'],
                    "POTONGAN_RUPIAH" => $data['POTONGAN_RUPIAH'],
                ]);

                $item = DB::table('ud84_master_produk')->where('NAMA',$data['NAMA'])->first();
                $tipeItem = $data['TIPE'];

                $logEntries = [];

                if($tipeItem == 'Pieces'){
                    $stokDecrease = [
                        "STOK"  => $item->STOK - $data['QUANTITY']
                    ];
                } else if($tipeItem == 'Satuan'){
                    $stokDecrease = [
                        "STOK"  => $item->STOK - ( $data['QUANTITY'] * $item->JUMLAH_PER_ITEM )
                    ];
                }

                $logEntries[] = [
                    "KODE_ITEM"  => $data['ID'],
                    "NAMA_ITEM"  => $data['NAMA'],
                    "ASAL"       => 'Retail',
                    "MASUK"      => 0,
                    "KELUAR"     => $stokDecrease['STOK'],
                    "STOK_FINAL" => $item->STOK - $stokDecrease['STOK'],
                    "CREATED_AT" => now()
                ];

                if (!empty($logEntries)) {
                    DB::table('ud84_logs')->insert($logEntries);
                }

                DB::table('ud84_master_produk')->where('NAMA',$data['NAMA'])->update($stokDecrease);
            }
        DB::commit();

        return response()->json([
            'status'    => 'success',
            'message'   => 'Data tersimpan!'
        ],200);
    }
}
