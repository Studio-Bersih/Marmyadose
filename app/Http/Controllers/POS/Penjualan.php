<?php

namespace App\Http\Controllers\POS;

use App\DTO\Responses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class Penjualan extends Controller
{
    public function saveTransaction(Request $request): JsonResponse{
        $cabang         = (int) $request->input('cabangAsal');
        $carts          = $request->input('cart');
        $tunai          = $request->input('cash');
        $totalTransaksi = $request->input('totalTransaksi');
        $kembali        = $request->input('kembalian');
        $keterangan     = $request->input('keterangan');
        $staff          = $request->input('pic');
        $usaha          = $request->input('usaha');

        if (empty($carts)) {
            return response()->json(new Responses(
                "error", "Harap mengisi keranjang terlebih dahulu!"
            ));
        }

        // Map cabang to correct stock field
        $stokField = match ($cabang) {
            1 => 'STOK_ITEM',
            2 => 'STOK_ITEM_SECOND',
            3 => 'STOK_ITEM_THIRD',
            default => 'STOK_ITEM',
        };

        $uniqueId = uniqid();
        $timestamp = now();
        $data = [];
        $logs = [];

        DB::beginTransaction();
        try {
            foreach ($carts as $cart) {
                // Lock the product row to avoid race condition
                $product = DB::table('pos_master_produk')
                    ->where('ID', $cart['id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw new \Exception("Produk {$cart['name']} tidak ditemukan.");
                }

                $stokSebelum = $product->$stokField;

                // --- STOCK VALIDATION ---
                // Debug mode: allow minus stock
                // if ($stokSebelum < $cart['amount']) {
                // return response()->json(new Responses(
                //     "error", "Stok produk \"" . $cart['name'] . "\" tidak mencukupi."
                // ));
                // }

                // Update stock (can go negative if debug mode is active)
                $stokSesudah = $stokSebelum - $cart['amount'];

                DB::table('pos_master_produk')
                    ->where('ID', $cart['id'])
                    ->update([
                        $stokField => $stokSesudah
                    ]);

                // Prepare detail data
                $data[] = [
                    "KEYS"          => $uniqueId,
                    "TOKEN"         => $staff,
                    "KODE"          => $cart['id'],
                    "NAMA"          => $cart['name'],
                    "JUMLAH"        => $cart['amount'],
                    "HARGA_STOK"    => $product->HARGA_STOK,
                    "HARGA_JUAL"    => $cart['hargaJual'],
                    "SISA_STOK"     => $stokSesudah,
                    "CREATED_AT"    => $timestamp
                ];

                // Prepare log entry
                $logs[] = [
                    "USAHA" => $usaha,
                    "TOKEN" => $staff,
                    "TEXT"  => "Transaksi penjualan oleh $staff: {$cart['name']} sejumlah {$cart['amount']}.",
                    "CREATED_AT" => $timestamp,
                    "UPDATED_AT" => $timestamp
                ];
            }

            // Insert into rekap
            DB::table('pos_penjualan_rekap')->insert([
                "KEYS"              => $uniqueId,
                "TOKEN"             => $staff,
                "CASH"              => $tunai,
                "KEMBALI"           => $kembali,
                "TOTAL_TRANSAKSI"   => $totalTransaksi,
                "KETERANGAN"        => $keterangan,
                "CREATED_AT"        => $timestamp
            ]);

            // Insert logs & details
            DB::table('pos_log')->insert($logs);
            DB::table('pos_penjualan_detail')->insert($data);

            DB::commit();

            return response()->json(new Responses(
                "success", "Transaksi berhasil"
            ));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(new Responses(
                "error", $e->getMessage()
            ));
        }
    }
}
