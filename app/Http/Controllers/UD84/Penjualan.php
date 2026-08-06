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

            // Points are computed before the insert so the granted figure can
            // be stored on the sale. A cancellation then reverses exactly what
            // was given, instead of recomputing under a rule that may have
            // changed since.
            $perPoin      = (int) config('ud84.poin_per_rupiah');
            $poinTambahan = ($cash > 0 && $perPoin > 0) ? (int) floor($cash / $perPoin) : 0;
            $poinDiberikan = 0;

            if ($poinTambahan > 0) {
                // Ensure NAMA matches and exists before incrementing
                $affectedRows = DB::table('ud84_member')
                    ->whereRaw("TRIM(NAMA) = ?", [trim($namaMember)])
                    ->increment('POINT', $poinTambahan);

                if ($affectedRows === 0) {
                    Log::warning("Point update failed for member: $namaMember. Check if the name exists.");
                } else {
                    $poinDiberikan = $poinTambahan;
                }
            }

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
                "MEMBER"            => $namaMember,
                "POIN"              => $poinDiberikan
            ]);

            foreach($request->input('CART') as $data){
                // Looked up by ID, not name: a cancellation has to find this
                // product again later, and two products sharing a name -- or a
                // rename between sale and cancel -- would adjust the wrong row.
                $item     = DB::table('ud84_master_produk')->where('ID',$data['ID'])->first();
                $tipeItem = $data['TIPE'];

                if (empty($item)) {
                    throw new \RuntimeException("Produk '{$data['NAMA']}' (ID {$data['ID']}) tidak ditemukan.");
                }

                DB::table('ud84_penjualan_detail')->insert([
                    "UNIQUE"          => $uniqueID,
                    "KODE"            => $data['ID'],
                    "NAMA"            => $data['NAMA'],
                    "SATUAN"          => $tipeItem === 'Pieces' ? 'Pcs' : ($item->TIPE ?? null),
                    "JUMLAH"          => $data['QUANTITY'],
                    "HARGA_ASLI"      => $data['HARGA_ASLI'],
                    "HARGA_TERJUAL"   => $data['TOTAL'],
                    "POTONGAN_PERSEN" => $data['POTONGAN_PERSEN'],
                    "POTONGAN_RUPIAH" => $data['POTONGAN_RUPIAH'],
                ]);

                // Declared inside the loop. It used to persist across
                // iterations, so a line with an unrecognised TIPE silently
                // reapplied the previous line's decrement to a different
                // product instead of failing.
                if($tipeItem == 'Pieces'){
                    $stokDecrease = [
                        "STOK"  => $item->STOK - $data['QUANTITY']
                    ];
                } else if($tipeItem == 'Satuan'){
                    $stokDecrease = [
                        "STOK"  => $item->STOK - ( $data['QUANTITY'] * $item->JUMLAH_PER_ITEM )
                    ];
                } else {
                    throw new \RuntimeException("Tipe penjualan '{$tipeItem}' tidak dikenal untuk produk '{$data['NAMA']}'.");
                }

                DB::table('ud84_logs')->insert([
                    "KODE_ITEM"  => $item->ID,
                    "NAMA_ITEM"  => $item->NAMA,
                    "ASAL"       => 'Retail',
                    "MASUK"      => 0,
                    "KELUAR"     => $item->STOK - $stokDecrease['STOK'],
                    "STOK_FINAL" => $stokDecrease['STOK'],
                    "CREATED_AT" => now()
                ]);

                DB::table('ud84_master_produk')->where('ID',$item->ID)->update($stokDecrease);
            }
            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Data tersimpan!',
                'data'      => ['UNIQUE' => $uniqueID]
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
