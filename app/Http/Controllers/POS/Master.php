<?php

namespace App\Http\Controllers\POS;

use App\DTO\Responses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class Master extends Controller
{
    public function getItem(Request $request): JsonResponse {
        $usaha = $request->input('USAHA'); // e.g., 'Nick Cell'
        $cabang = (int) $request->input('CABANG'); // 1, 2, or 3

        // Determine which stock field to use based on CABANG
        $stokField = match ($cabang) {
            1 => 'STOK_ITEM',
            2 => 'STOK_ITEM_SECOND',
            3 => 'STOK_ITEM_THIRD',
            default => 'STOK_ITEM' // fallback just in case
        };

        // Construct the query
        $data = DB::table('pos_master_produk')->where('USAHA', $usaha)->orderBy('NAMA')->select([
            'ID',
            'NAMA',
            'BARCODE',
            'JENIS',
            DB::raw("$stokField AS STOK_ITEM"),
            'HARGA_STOK',
            'HARGA_JUAL',
            'KETERANGAN',
        ])->get();

        return response()->json(new Responses(
            "success",
            "Item berhasil dimuat!",
            $data
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
                "USAHA"         => $request->input('usaha')
            ]);
        DB::commit();

        return response()->json(new Responses(
            "success","Item berhasil dibuat!"
        ));
    }

    public function detailItem(Request $request): JsonResponse {
        $DB = DB::table('pos_master_produk')->where('ID', $request->input('id'))->first();
        return response()->json(new Responses(
            "success","Item berhasil dimuat!", $DB
        ));
    }

    public function updateItem(Request $request): JsonResponse {
        DB::beginTransaction();
            DB::table('pos_master_produk')->where('ID', $request->input('id'))->update([
                "NAMA"          => $request->input('name'),
                "BARCODE"       => $request->input('barcode'),
                "JENIS"         => $request->input('jenis'),
                "STOK_ITEM"     => $request->input('stok'),
                "HARGA_STOK"    => $request->input('hargaStok'),
                "HARGA_JUAL"    => $request->input('hargaJual'),
                "KETERANGAN"    => $request->input('keterangan'),
            ]);
        DB::commit();

        return response()->json(new Responses(
            "success","Item berhasil diupdate!"
        ));
    }

    public function updateStock(Request $request): JsonResponse {
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
