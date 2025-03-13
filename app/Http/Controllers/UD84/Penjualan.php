<?php

namespace App\Http\Controllers\UD84;

use DB;
use Log;
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

        DB::beginTransaction();
        try {

            $dp = $request->input('DP');
            $cash = $request->input('CASH');
            $potongan = $request->input('POTONGAN');
            $jatuhTempo = $request->input('JATUH_TEMPO');
            $total = $request->input('TOTAL');
            $keterangan = $request->input('KETERANGAN');

            DB::table('ud84_penjualan_rekap')->insert([
                "UNIQUE"            => $uniqueID,
                "NAMA"              => $namaMember,
                "DP"                => $dp,
                "CASH"              => $cash,
                "KEMBALIAN"         => empty($cash) ? 0 : $cash - ($total - $potongan) ,
                "POTONGAN"          => $potongan,
                "JATUH_TEMPO"       => $jatuhTempo ?? NULL,
                "TOTAL"             => $total - $potongan,
                "KETERANGAN"        => $keterangan,
                "MEMBER"            => $namaMember
            ]);

            if ($cash > 0) {
                $poinTambahan = floor($cash / 500000);
            
                if ($poinTambahan > 0) {
                    // Ensure NAMA matches and exists before incrementing
                    $affectedRows = DB::table('ud84_member')
                        ->whereRaw("TRIM(NAMA) = ?", [trim($namaMember)])
                        ->increment('POINT', $poinTambahan);
            
                    if ($affectedRows === 0) {
                        Log::warning("Point update failed for member: $namaMember. Check if the name exists.");
                    }
                }
            }

            foreach($request->input('CART') as $data){
                DB::table('ud84_penjualan_detail')->insert([
                    "UNIQUE"          => $uniqueID,
                    "KODE"            => $data['ID'],
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
                    "KELUAR"     => $item->STOK - $stokDecrease['STOK'],
                    "STOK_FINAL" => $stokDecrease['STOK'],
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
        } catch (\Exception $e) {
            Log::info($e);
            DB::rollBack();
            return response()->json([
                'status'    => 'error',
                'message'   => 'Terjadi kesalahan: ' . $e->getMessage()
            ],500);
        }
    }

    
}
