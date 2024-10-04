<?php

namespace App\Http\Controllers\POS;

use App\DTO\Responses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class Report extends Controller
{
    public function downloadReport(Request $request): JsonResponse {
        $type = $request->input('type');
        
        $data = [];
        if($type === 'dailyReport') {
            $date   = $request->input('date');
            $pic    = $request->input('pic');

            $DB = DB::table('pos_penjualan_detail')->where('TOKEN',$pic)->whereDate('created_at', DB::raw('CURDATE()'))->get();

            $data = [];
            foreach($DB as $DB) {
                $originalPrice = DB::table('pos_master_produk')->where('ID', $DB->KODE)->first('HARGA_STOK');
                $data[] = [
                    "Nama Item"         => $DB->NAMA,
                    "Terjual"           => $DB->JUMLAH,
                    "Sisa Stok"         => $DB->SISA_STOK,
                    "Harga Beli"        => $originalPrice->HARGA_STOK,
                    "Harga Jual"        => $DB->HARGA_JUAL,
                    "Total Transaksi"   => $DB->JUMLAH * $DB->HARGA_JUAL,
                    "Waktu Transaksi"   => date("d/m/Y H:i", strtotime($DB->CREATED_AT)),
                ];
            }

            return response()->json(new Responses(
                "success","Data berhasil dimuat!", $data
            ));
        }

        return response()->json(new Responses(
            "success","Data berhasil dimuat!", $data
        ));
    }
}
