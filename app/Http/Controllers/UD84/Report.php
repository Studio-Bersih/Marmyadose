<?php

namespace App\Http\Controllers\UD84;

use DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class Report extends Controller
{

    public function confirmPassword(Request $request) {
        $password = $request->input('password');

        if($password === "UD84-Alul") {
            return response()->json([
                "status" => "success",
                "message" => "Authenticated"
            ],200);
        }

        return response()->json([
            "status" => "error",
            "message" => "Password anda salah."
        ],200);
    }

    public function commonCharts(){
        $dataOmset          = DB::table('ud84_penjualan_rekap')->whereMonth('CREATED_AT',Carbon::now()->month)->sum('TOTAL');
        $dataOperasional    = DB::table('ud84_operasional')->whereMonth('CREATED_AT',Carbon::now()->month)->sum('NOMINAL');
        return response()->json([
            "status"    => "success",
            "message"   => "Loaded",
            "data"      => [
                "BULAN"         => Carbon::parse(now())->translatedFormat('F Y'),
                "OMSET"         => $dataOmset,
                "OPERASIONAL"   => $dataOperasional,
                "BERSIH"        => $dataOmset - $dataOperasional,
            ]
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
            "status" => "success",
            "message" => "Loaded",
            "data" => [
                "BULAN"             => Carbon::parse(now())->translatedFormat('F Y'),
                "OMSET"             => $monthDetail,
                "OPERASIONAL"       => $listOperational,
                "OPERASIONAL_SUM"   => $operationalSum
            ]
        ],200);
    }

    public function daftarTransaksi(){
        $data = DB::table('ud84_penjualan_rekap')->skip(0)->take(10)->orderByDesc('ID')->get();
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
            "status" => "success",
            "message" => "Loaded",
            "data" => [
                "data"          => $listData,
                "DP"            => array_sum($nominalDP),
                "TRANSAKSI"     => array_sum($nominalTransaksi),
                "BAYAR_TUNAI"   => array_sum($nominalBayarTunai)
            ]
        ],200);
    }

    public function searchTransaksi(Request $request) {
        $startDate = $request->input('start');
        $endDate   = $request->input('end');

        // Validasi tanggal tidak kosong
        if (!$startDate || !$endDate) {
            return response()->json([
                "status"  => "error",
                "message" => "Harap masukkan tanggal awal dan akhir."
            ], 400);
        }

        // Pastikan endDate tidak lebih awal dari startDate
        if (strtotime($endDate) < strtotime($startDate)) {
            return response()->json([
                "status"  => "error",
                "message" => "Tanggal akhir tidak boleh lebih awal dari tanggal mulai."
            ], 400);
        }

        // Query pencarian data
        $data = DB::table('ud84_penjualan_rekap')
            ->whereBetween('CREATED_AT', [$startDate, $endDate])
            ->orderByDesc('ID')
            ->get();

        // Inisialisasi variabel
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
            "status" => "success",
            "message" => "Loaded",
            "data" => [
                "data"          => $listData,
                "DP"            => array_sum($nominalDP),
                "TRANSAKSI"     => array_sum($nominalTransaksi),
                "BAYAR_TUNAI"   => array_sum($nominalBayarTunai)
            ]
        ],200);
    }

    public function detailTransaksi($ID){
        $rekap  = DB::table('ud84_penjualan_rekap')->where('UNIQUE',$ID)->first();
        $detail = DB::table('ud84_penjualan_detail')->where('UNIQUE',$ID)->get();

        return response()->json([
            "status"    => "success",
            "message"   => "Loaded",
            "data"      => [
                "rekap"     => $rekap,
                "detail"    => $detail
            ]
        ],200);
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
            "status" => "success",
            "message" => "Loaded",
            "data"  => [
                "tanggal"   => !empty($dataRekap->CREATED_AT) ? Carbon::parse($dataRekap->CREATED_AT)->translatedFormat('d F Y') : Carbon::now()->translatedFormat('d F Y'),
                "tuan"      => $dataRekap->NAMA ?? '-',
                "total"     => array_sum($totalSum) ?? 0,
                "data"      => $listDetail ?? [],
                "rekap"     => $dataRekap
            ]
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
        return response()->json([
            "status"    => "success",
            "message"   => "Loaded",
            "data"      => $listData
        ],200);
    }

    public function singleItem(Request $request){
        $getItem    = DB::table('ud84_master_produk')->where('ID',$request->input('ID'))->first();
        $getItems   = DB::table('ud84_penjualan_detail')->where('NAMA',$getItem->NAMA)->whereBetween('CREATED_AT',[
            $request->input('START'),
            $request->input('FINISH'),
        ])->get();

        $listItem           = [];
        $totalKotor         = [];
        $potonganRupiah     = [];
        $potonganPersen     = [];
        $totalPieces        = [];

        foreach($getItems as $data){
            $listItem[] = [
                "CREATED_AT"        => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y, h:i'),
                "NAMA"              => $data->NAMA,
                "POTONGAN_RUPIAH"   => $data->POTONGAN_RUPIAH,
                "POTONGAN_PERSEN"   => $data->POTONGAN_PERSEN,
                "JUMLAH"            => $data->JUMLAH,
                "NOMINAL"           => $data->HARGA_TERJUAL
            ];
            $totalKotor[]        = $data->HARGA_TERJUAL;
            $potonganRupiah[]    = $data->POTONGAN_RUPIAH;
            $potonganPersen[]    = $data->POTONGAN_PERSEN;
            $totalPieces[]       = $data->JUMLAH;
        }

        return response()->json([
            'data'                      => $listItem,
            'TOTAL_KOTOR'               => array_sum($totalKotor),
            'TOTAL_POTONGAN_RUPIAH'     => array_sum($potonganRupiah),
            'TOTAL_POTONGAN_PERSEN'     => array_sum($potonganPersen),
            'TOTAL_PIECES'              => array_sum($totalPieces)
        ],200);
    }

    public function updateDP(Request $request){
        DB::table('ud84_penjualan_rekap')->where('UNIQUE',$request->input('KODE'))->update([
            "DP"    => $request->input('DP') + $request->input('OLD_DP')
        ]);
        return response()->json([
            "status"    => "success",
            "message"   => "DP Updated!"
        ],200);
    }

}
