<?php

namespace App\Http\Controllers\UD84;

use DB;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Report extends Controller
{
    public function daftarTransaksi(){
        $data = DB::table('ud84_penjualan_rekap')->skip(0)->take(500)->orderByDesc('ID')->get();
        $listData = $nominalTransaksi = $nominalDP = $nominalBayarTunai = [];
        foreach($data as $data){
            $listData[] = [
                "ID"            => $data->UNIQUE,
                "TANGGAL"       => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y'),
                "JATUH_TEMPO"   => empty($data->JATUH_TEMPO) ? '-' : Carbon::parse($data->JATUH_TEMPO)->translatedFormat('d F Y'),
                "NAMA"          => empty($data->NAMA) ? 'UMUM' : ucwords(trans($data->NAMA)),
                "NOMINAL"       => $data->TOTAL,
                "DP"            => empty($data->DP) ? 0 : $data->DP,
                "BAYAR_TUNAI"   => empty($data->CASH) ? 0 : $data->CASH,
            ];
            $nominalDP[]            = $data->DP;
            $nominalTransaksi[]     = $data->TOTAL;
            $nominalBayarTunai[]    = $data->CASH;
        }
        return response()->json([
            "data"          => $listData,
            "DP"            => array_sum($nominalDP),
            "TRANSAKSI"     => array_sum($nominalTransaksi),
            "BAYAR_TUNAI"   => array_sum($nominalBayarTunai)
        ],200);
    }

    public function detailTransaksi($ID){
        $data = DB::table('ud84_penjualan_detail')->where('UNIQUE',$ID)->get();
        return response()->json($data,200);
    }
}
