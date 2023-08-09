<?php

namespace App\Http\Controllers\UD84;

use DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class Report extends Controller
{

    public function commonCharts(){
        $dataOmset          = DB::table('ud84_penjualan_rekap')->whereMonth('CREATED_AT',Carbon::now()->month)->sum('TOTAL');
        $dataOperasional    = DB::table('ud84_operasional')->whereMonth('CREATED_AT',Carbon::now()->month)->sum('NOMINAL');
        return response()->json([
            "BULAN"         => Carbon::parse(now())->translatedFormat('F Y'),
            "OMSET"         => $dataOmset,
            "OPERASIONAL"   => $dataOperasional,
            "BERSIH"        => $dataOmset - $dataOperasional,
        ],200);
    }

    public function postOperasional(Request $request){
        DB::table('ud84_operasional')->insert([
            "NAMA"          => $request->input('NAMA'),
            "TANGGAL"       => $request->input('TANGGAL'),
            "NOMINAL"       => $request->input('NOMINAL'),
            "KETERANGAN"    => $request->input('KETERANGAN'),
        ]);
        return response()->json("OK",200);
    }

    public function getReportOperasional(){
        $operationalDetail  = DB::table('ud84_operasional')->whereMonth('CREATED_AT',Carbon::now()->month)->orderByDesc('ID')->get();
        $listOperasional = [];
        foreach($operationalDetail as $data){
            $listOperasional[] = [
                "NAMA"          => $data->NAMA,
                "TANGGAL"       => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y, H:i'),
                "NOMINAL"       => $data->NOMINAL,
                "KETERANGAN"    => $data->KETERANGAN
            ];
        }
        return response()->json($listOperasional,200);
    }

    public function omsetDetail(){
        $monthDetail = DB::table('ud84_penjualan_detail')
        ->whereMonth('CREATED_AT',Carbon::now()->month)
        ->groupBy('NAMA')
        ->select(DB::raw("
            SUM(JUMLAH) as 'JUMLAH',
            SUM(HARGA_TERJUAL) as 'TOTAL'
        "),'NAMA')
        ->skip(0)->take(10)->get();

        $operationalDetail  = DB::table('ud84_operasional')->whereMonth('CREATED_AT',Carbon::now()->month)->orderByDesc('NOMINAL')->skip(0)->take(10)->get();
        $operationalSum     = DB::table('ud84_operasional')->whereMonth('CREATED_AT',Carbon::now()->month)->sum('NOMINAL');

        $listOperational = [];
        foreach($operationalDetail as $data){
            $listOperational[] = [
                "NAMA"          => $data->NAMA,
                "KETERANGAN"    => $data->KETERANGAN,
                "NOMINAL"       => $data->NOMINAL,
                "CREATED_AT"    => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y')
            ];
        }

        return response()->json([
            "BULAN"             => Carbon::parse(now())->translatedFormat('F Y'),
            "OMSET"             => $monthDetail,
            "OPERASIONAL"       => $listOperational,
            "OPERASIONAL_SUM"   => $operationalSum
        ],200);
    }

    public function daftarTransaksi(){
        $data = DB::table('ud84_penjualan_rekap')->skip(0)->take(2000)->orderByDesc('ID')->get();
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

    public function getInvoices($ID){
        $dataRekap  = DB::table('ud84_penjualan_rekap')->where('UNIQUE',$ID)->first();
        $dataDetail = DB::table('ud84_penjualan_detail')->where('UNIQUE',$ID)->get();

        $listDetail = [];
        $totalSum   = [];
        foreach($dataDetail as $data){
            $listDetail[] = [
                "QUANTITY"  => $data->JUMLAH,
                "NAMA"      => $data->NAMA,
                "HARGA"     => $data->HARGA_TERJUAL,
                "JUMLAH"    => $data->HARGA_TERJUAL * $data->JUMLAH
            ];
            $totalSum[]     = $data->HARGA_TERJUAL;
        }

        return response()->json([
            "TANGGAL"   => Carbon::parse($dataRekap->CREATED_AT)->translatedFormat('d F Y'),
            "TUAN"      => $dataRekap->NAMA,
            "TOTAL"     => array_sum($totalSum),
            "DATA"      => $listDetail
        ],200);
    }

    public function singleItemReport($ID){
        $dataDetail = DB::table('ud84_penjualan_detail')->where('NAMA',$ID)->whereMonth('CREATED_AT',Carbon::now()->month)->orderByDesc('ID')->get();
        $listData = [];
        foreach($dataDetail as $data){
            $listData[] = [
                "NAMA"              => $data->NAMA,
                "JUMLAH"            => $data->JUMLAH,
                "HARGA_ASLI"        => $data->HARGA_ASLI,
                "HARGA_TERJUAL"     => $data->HARGA_TERJUAL,
                "CREATED_AT"        => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y'),
            ];
        }
        return response()->json($listData,200);
    }
}
