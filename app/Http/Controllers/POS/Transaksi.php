<?php

namespace App\Http\Controllers\POS;

use App\DTO\Responses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class Transaksi extends Controller
{
    public function transaksiPenjualan(): JsonResponse {
        $DB = DB::table('pos_penjualan_detail')->where('TOKEN','AAA')->whereDate('created_at', DB::raw('CURDATE()'))->get();

        $data = [];
        foreach($DB as $DB) {
            $originalPrice = DB::table('pos_master_produk')->where('ID', $DB->KODE)->first('HARGA_STOK');
            $data[] = [
                "NAMA" => $DB->NAMA,
                "TERJUAL" => $DB->JUMLAH,
                "SISA_STOK" => $DB->SISA_STOK,
                "HARGA_BELI" => $originalPrice->HARGA_STOK,
                "HARGA_JUAL" => $DB->HARGA_JUAL,
                "TOTAL_TRANSAKSI" => $DB->JUMLAH * $DB->HARGA_JUAL,
                "WAKTU_TRANSAKSI" => date("d/m/Y H:i", strtotime($DB->CREATED_AT)),
            ];
        }

        return response()->json(new Responses(
            "status", 'Data berhasil dimuat!', $data
        ));
    }
}
