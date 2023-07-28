<?php

namespace App\Http\Controllers\UD84;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MasterProduk extends Controller
{
    public function getMasterProduct(){
        $data = DB::table('ud84_master_produk')->get();
        return response()->json($data,200);
    }

    public function postMasterProduct(Request $request){
        DB::table('ud84_master_produk')->insert([
            "NAMA"          => $request->input('NAMA'),
            "STOK"          => $request->input('STOK'),
            "TIPE"          => $request->input('TIPE'),
            "STATUS_JUAL"   => $request->input('STATUS_JUAL'),
            "DISTRIBUTOR"   => $request->input('DISTRIBUTOR'),
            "HARGA_PABRIK"  => $request->input('HARGA_PABRIK'),
            "HARGA_JUAL"    => $request->input('HARGA_JUAL'),
            "DESKRIPSI"     => $request->input('DESKRIPSI')
        ]);
        return response()->json([
            'status'    => 'success',
            'message'   => 'Item berhasil disimpan!'
        ],200);
    }

    public function updateMasterProduct(Request $request){
        DB::table('ud84_master_produk')->where('ID', $request->input('ID') )->update([
            "NAMA"          => $request->input('NAMA'),
            "STOK"          => $request->input('STOK'),
            "TIPE"          => $request->input('TIPE'),
            "STATUS_JUAL"   => $request->input('STATUS_JUAL'),
            "DISTRIBUTOR"   => $request->input('DISTRIBUTOR'),
            "HARGA_PABRIK"  => $request->input('HARGA_PABRIK'),
            "HARGA_JUAL"    => $request->input('HARGA_JUAL'),
            "DESKRIPSI"     => $request->input('DESKRIPSI')
        ]);
        return response()->json([
            'status'    => 'success',
            'message'   => 'Item berhasil diudpate!'
        ],200);
    }

    public function deleteMasterProduct(Request $request){
        DB::table('ud84_master_produk')->where('ID', $request->input('ID'))->delete();
        return response()->json([
            'status'    => 'success',
            'message'   => 'Item berhasil dihapus!'
        ],200);
    }
}
