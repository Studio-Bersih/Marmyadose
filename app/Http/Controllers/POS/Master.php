<?php

namespace App\Http\Controllers\POS;

use App\DTO\Responses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function masterProduct(Request $request): JsonResponse { // Belom ada rute
        $usaha = $request->input('USAHA'); // It's string. The value is: Nick Cell
        $data = DB::table('pos_master_produk')->where('USAHA', $usaha)->orderBy('NAMA')->get([
            "ID","NAMA","BARCODE","JENIS","STOK_ITEM", "STOK_ITEM_SECOND", "STOK_ITEM_THIRD","HARGA_STOK","HARGA_JUAL","KETERANGAN"
        ]);
        
        return response()->json(new Responses(
            "success","Item berhasil dimuat!", $data
        ));
    }

    public function createItem(Request $request): JsonResponse{
        $barcode = $request->input('barcode');
        $usaha   = $request->input('usaha');

        // Check if item with the same barcode & usaha already exists
        $existingItem = DB::table('pos_master_produk')
            ->where('BARCODE', $barcode)
            ->where('USAHA', $usaha)
            ->first();

        if ($existingItem) {
            return response()->json([
                "status" => 'error',
                "message" => "Item dengan barcode '$barcode' untuk usaha '$usaha' sudah ada!"
            ], 200); // Use 409 Conflict for duplicate entries
        }

        try {
            DB::beginTransaction();

            DB::table('pos_master_produk')->insert([
                "NAMA"              => $request->input('name'),
                "BARCODE"           => $barcode,
                "JENIS"             => $request->input('jenis'),
                "STOK_ITEM"         => $request->input('stok'),
                "STOK_ITEM_SECOND"  => 0,
                "STOK_ITEM_THIRD"   => 0,
                "HARGA_STOK"        => $request->input('hargaStok'),
                "HARGA_JUAL"        => $request->input('hargaJual'),
                "KETERANGAN"        => $request->input('keterangan'),
                "USAHA"             => $usaha
            ]);

            DB::commit();

            return response()->json([
                "status" => 'success',
                "message" => "Item berhasil dibuat!"
            ], 200); // 201 Created, lebih semantik
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Gagal membuat item:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                "status" => 'error',
                "message" => "Gagal membuat item: " . $e->getMessage()
            ], 500);
        }
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
            "STOK_ITEM"         => $request->input('stock'),
            "STOK_ITEM_SECOND"  => $request->input('secondStock'),
            "STOK_ITEM_THIRD"   => $request->input('thirdStock'),
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

    public function itemTransfer(Request $request): JsonResponse {
        $cart = $request->input('cart');
        $cabangAsal = (int) $request->input('cabangAsal');
        $cabangTujuan = (int) $request->input('cabangTujuan');
        $usaha = $request->input('usaha');
        $staff = $request->input('pic'); // same as TOKEN

        try {
            DB::beginTransaction();

            foreach ($cart as $item) {
                $itemId = $item['id'];
                $itemName = $item['name'];
                $jumlah = (int) $item['amount'];

                // Map cabang to field name
                $fieldAsal = match($cabangAsal) {
                    1 => 'STOK_ITEM',
                    2 => 'STOK_ITEM_SECOND',
                    3 => 'STOK_ITEM_THIRD',
                };

                $fieldTujuan = match($cabangTujuan) {
                    1 => 'STOK_ITEM',
                    2 => 'STOK_ITEM_SECOND',
                    3 => 'STOK_ITEM_THIRD',
                };

                // Get current stock & item name
                $produk = DB::table('pos_master_produk')
                    ->where('ID', $itemId)
                    ->where('USAHA', $usaha)
                    ->first(['NAMA', $fieldAsal]);

                if (!$produk) {
                    throw new \Exception("Produk dengan ID $itemName tidak ditemukan.");
                }

                if ($produk->$fieldAsal < $jumlah) {
                    throw new \Exception("Stok tidak cukup untuk item \"{$produk->NAMA}\".");
                }

                // Decrease from asal
                DB::table('pos_master_produk')
                    ->where('ID', $itemId)
                    ->where('USAHA', $usaha)
                    ->decrement($fieldAsal, $jumlah);

                // Increase to tujuan
                DB::table('pos_master_produk')
                    ->where('ID', $itemId)
                    ->where('USAHA', $usaha)
                    ->increment($fieldTujuan, $jumlah);
            }

            // 📝 Log the transfer
            DB::table('pos_log')->insert([
                'USAHA'     => $usaha,
                'TOKEN'     => $staff,
                'TEXT'      => "Memindahkan stok antar cabang dari Cabang $cabangAsal ke Cabang $cabangTujuan sejumlah " . count($cart) . " item.",
                'CREATED_AT'=> now(),
                'UPDATED_AT'=> now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Stok berhasil dipindahkan antar cabang!',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function itemMasuk(Request $request): JsonResponse{
        $cart = $request->input('cart');
        $cabangTujuan = (int) $request->input('cabangTujuan');
        $usaha = $request->input('usaha');
        $staff = $request->input('pic'); // same as TOKEN

        try {
            DB::beginTransaction();

            $logDetails = [];

            foreach ($cart as $item) {
                $itemId = $item['id'];
                $itemName = $item['name'];
                $jumlah = (int) $item['amount'];

                // Map cabang tujuan to field name
                $fieldTujuan = match($cabangTujuan) {
                    1 => 'STOK_ITEM',
                    2 => 'STOK_ITEM_SECOND',
                    3 => 'STOK_ITEM_THIRD',
                    default => throw new \Exception("Cabang tujuan tidak valid.")
                };

                // Check if the product exists
                $produk = DB::table('pos_master_produk')
                    ->where('ID', $itemId)
                    ->where('USAHA', $usaha)
                    ->first(['NAMA', $fieldTujuan]);

                if (!$produk) {
                    throw new \Exception("Produk dengan ID $itemName tidak ditemukan.");
                }

                // Increase stock
                DB::table('pos_master_produk')
                    ->where('ID', $itemId)
                    ->where('USAHA', $usaha)
                    ->increment($fieldTujuan, $jumlah);

                // Add to log details
                $logDetails[] = "{$produk->NAMA} (+{$jumlah})";
            }

            // 📝 Log the stock addition
            DB::table('pos_log')->insert([
                'USAHA'      => $usaha,
                'TOKEN'      => $staff,
                'TEXT'       => "Menambahkan stok ke Cabang $cabangTujuan: " . implode(', ', $logDetails),
                'CREATED_AT' => now(),
                'UPDATED_AT' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Stok berhasil ditambahkan!',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

}
