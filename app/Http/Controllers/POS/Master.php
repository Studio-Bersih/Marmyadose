<?php

namespace App\Http\Controllers\POS;

use App\DTO\Responses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class Master extends Controller
{
    public function getItem(): JsonResponse {
        $data = DB::table('pos_master_produk')->orderBy('NAMA')->get([
            "ID","NAMA","BARCODE","JENIS","STOK_ITEM","HARGA_STOK","HARGA_JUAL","KETERANGAN"
        ]);
        
        return response()->json(new Responses(
            "success","Item berhasil dimuat!", $data
        ));
    }

    public function createItem(Request $request): JsonResponse {
        $barcode = $request->input('barcode');
        $existingItem = DB::table('pos_master_produk')->where('BARCODE', $barcode)->first();

        if ($existingItem) {
            return response()->json(new Responses(
                "error", "Item dengan barcode ini sudah ada!"
            ), 400);
        }

        DB::beginTransaction();
            DB::table('pos_master_produk')->insert([
                "NAMA"          => $request->input('name'),
                "BARCODE"       => $barcode,
                "JENIS"         => $request->input('jenis'),
                "STOK_ITEM"     => $request->input('stok'),
                "HARGA_STOK"    => $request->input('hargaStok'),
                "HARGA_JUAL"    => $request->input('hargaJual'),
                "KETERANGAN"    => $request->input('keterangan'),
            ]);
        DB::commit();

        return response()->json(new Responses(
            "success","Item berhasil dibuat!"
        ));
    }

    public function updateItem(Request $request): JsonResponse {
        DB::table('pos_master_produk')->where('ID', $request->input('id'))->update([
            "STOK_ITEM" => $request->input('stock')
        ]);
        return response()->json(new Responses(
            "success", "Stok berhasil diupdate!"
        ), 200);
    }

    public function deleteItem(Request $request): JsonResponse {
        DB::table('pos_master_produk')->where('ID', $request->input('id'))->delete();
        return response()->json(new Responses(
            "success", "Item berhasil dihapus!"
        ), 200);
    }
}
