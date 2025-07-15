<?php

namespace App\Http\Controllers\POS;

use App\DTO\Responses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class Penjualan extends Controller
{
    public function saveTransaction(Request $request): JsonResponse {
        $cabang         = (int) $request->input('cabangAsal'); // 👈 get cabang
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

        $cartIds = array_column($carts, 'id');
        $products = DB::table('pos_master_produk')->whereIn('ID', $cartIds)->get()->keyBy('ID');

        foreach ($carts as $cart) {
            $product = $products->get($cart['id']);

            if (!$product) {
                return response()->json(new Responses(
                    "error", "Produk dengan nama " . $cart['name'] . " tidak ditemukan."
                ));
            }

            $stokSebelum = $product->$stokField;

            if ($stokSebelum < $cart['amount']) {
                return response()->json(new Responses(
                    "error", "Stok produk \"" . $cart['name'] . "\" tidak mencukupi."
                ));
            }

            // Update data detail
            $data[] = [
                "KEYS"          => $uniqueId,
                "TOKEN"         => $staff,
                "KODE"          => $cart['id'],
                "NAMA"          => $cart['name'],
                "JUMLAH"        => $cart['amount'],
                "HARGA_STOK"    => $product->HARGA_STOK,
                "HARGA_JUAL"    => $cart['hargaJual'],
                "SISA_STOK"     => $stokSebelum - $cart['amount'],
                "CREATED_AT"    => $timestamp
            ];

            // Log entry
            $logs[] = [
                "USAHA" => $usaha,
                "TOKEN" => $staff,
                "TEXT"  => "Transaksi penjualan oleh $staff: {$cart['name']} sejumlah {$cart['amount']}.",
                "CREATED_AT" => $timestamp,
                "UPDATED_AT" => $timestamp
            ];

            // Update stock dynamically
            DB::table('pos_master_produk')
                ->where('ID', $cart['id'])
                ->update([
                    $stokField => $stokSebelum - $cart['amount']
                ]);
        }

        // Begin transaction after validation is done
        DB::beginTransaction();
        try {
            DB::table('pos_penjualan_rekap')->insert([
                "KEYS"              => $uniqueId,
                "TOKEN"             => $staff,
                "CASH"              => $tunai,
                "KEMBALI"           => $kembali,
                "TOTAL_TRANSAKSI"   => $totalTransaksi,
                "KETERANGAN"        => $keterangan,
                "CREATED_AT"        => $timestamp
            ]);

            DB::table('pos_log')->insert($logs);
            DB::table('pos_penjualan_detail')->insert($data);

            DB::commit();

            return response()->json(new Responses(
                "success", "Transaksi berhasil"
            ));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(new Responses(
                "error", "Gagal menyimpan transaksi. Silakan coba lagi."
            ));
        }
    }
}
