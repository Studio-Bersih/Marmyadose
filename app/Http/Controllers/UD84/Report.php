<?php

namespace App\Http\Controllers\UD84;

use DB;
use Log;
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

    public function salesPassword(Request $request) {
        $password = $request->input('password');

        if($password === "uvx321") {
            return response()->json([
                "status"    => "success",
                "message"   => "Authenticated",
                "data"      => "Standard"
            ],200);
        } else if ($password === "plm123") {
            return response()->json([
                "status"    => "success",
                "message"   => "Authenticated",
                "data"      => "Sales"
            ],200);
        }

        return response()->json([
            "status" => "error",
            "message" => "Password anda salah."
        ],200);
    }

    public function commonCharts(){
        $dataOmset          = DB::table('ud84_penjualan_rekap')->where('STATUS','Aktif')->whereMonth('CREATED_AT',Carbon::now()->month)->sum('TOTAL');
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
        // ud84_penjualan_detail has no status of its own, so cancelled sales
        // are excluded by joining to their rekap row.
        $monthDetail = DB::table('ud84_penjualan_detail as d')
        ->join('ud84_penjualan_rekap as r','r.UNIQUE','=','d.UNIQUE')
        ->where('r.STATUS','Aktif')
        ->whereMonth('d.CREATED_AT',Carbon::now()->month)
        ->groupBy('d.NAMA')
        ->select(DB::raw("
            SUM(d.JUMLAH) as 'JUMLAH',
            SUM(d.HARGA_TERJUAL) as 'TOTAL'
        "),'d.NAMA')
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
        $data = DB::table('ud84_penjualan_rekap')->where('STATUS','Aktif')->skip(0)->take(10)->orderByDesc('ID')->get();
        $listData = $nominalTransaksi = $nominalDP = $nominalBayarTunai = $nominalPotongan = $nominalKembalian = [];
        foreach($data as $data){
            $listData[] = [
                "ID"            => $data->UNIQUE,
                "TANGGAL"       => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y'),
                "JATUH_TEMPO"   => empty($data->JATUH_TEMPO) ? '-' : Carbon::parse($data->JATUH_TEMPO)->translatedFormat('d F Y'),
                "NAMA"          => empty($data->NAMA) ? 'UMUM' : ucwords(trans($data->NAMA)),
                "NOMINAL"       => $data->TOTAL,
                "KEMBALIAN"     => $data->KEMBALIAN,
                "DP"            => empty($data->DP) ? 0 : $data->DP,
                "BAYAR_TUNAI"   => empty($data->CASH) ? 0 : $data->CASH,
                "POTONGAN"      => empty($data->POTONGAN) ? 0 : $data->POTONGAN
            ];
            $nominalDP[]            = $data->DP;
            $nominalTransaksi[]     = $data->TOTAL;
            $nominalBayarTunai[]    = $data->CASH;
            $nominalPotongan[]      = $data->POTONGAN;
            $nominalKembalian[]     = $data->KEMBALIAN;
        }

        return response()->json([
            "status" => "success",
            "message" => "Loaded",
            "data" => [
                "data"          => $listData,
                "DP"            => array_sum($nominalDP),
                "TRANSAKSI"     => array_sum($nominalTransaksi),
                "BAYAR_TUNAI"   => array_sum($nominalBayarTunai),
                "POTONGAN"      => array_sum($nominalPotongan),
                "KEMBALIAN"     => array_sum($nominalKembalian)
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

        // Cancelled sales are hidden unless explicitly asked for.
        $tampilkanBatal = filter_var($request->input('TAMPILKAN_BATAL', false), FILTER_VALIDATE_BOOLEAN);

        $query = DB::table('ud84_penjualan_rekap')
            ->whereBetween('CREATED_AT', [$startDate, $endDate])
            ->orderByDesc('ID');

        if (!$tampilkanBatal) {
            $query->where('STATUS', 'Aktif');
        }

        $data = $query->get();

        // Inisialisasi variabel
        $listData = $nominalTransaksi = $nominalDP = $nominalBayarTunai = $nominalPotongan = $nominalKembalian = [];
        foreach($data as $data){
            $listData[] = [
                "ID"            => $data->UNIQUE,
                "STATUS"        => $data->STATUS,
                "TANGGAL"       => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y'),
                "JATUH_TEMPO"   => empty($data->JATUH_TEMPO) ? '-' : Carbon::parse($data->JATUH_TEMPO)->translatedFormat('d F Y'),
                "NAMA"          => empty($data->NAMA) ? 'UMUM' : ucwords(trans($data->NAMA)),
                "NOMINAL"       => $data->TOTAL,
                "KEMBALIAN"     => $data->KEMBALIAN,
                "DP"            => empty($data->DP) ? 0 : $data->DP,
                "BAYAR_TUNAI"   => empty($data->CASH) ? 0 : $data->CASH,
                "POTONGAN"      => empty($data->POTONGAN) ? 0 : $data->POTONGAN
            ];

            // Totals always exclude cancelled sales, even when the list is
            // showing them. A row displayed above a footer that counts it
            // would be worse than not showing the row at all.
            if ($data->STATUS === 'Dibatalkan') {
                continue;
            }

            $nominalDP[]            = $data->DP;
            $nominalTransaksi[]     = $data->TOTAL;
            $nominalBayarTunai[]    = $data->CASH;
            $nominalPotongan[]      = $data->POTONGAN;
            $nominalKembalian[]     = $data->KEMBALIAN;
        }

        return response()->json([
            "status" => "success",
            "message" => "Loaded",
            "data" => [
                "data"          => $listData,
                "DP"            => array_sum($nominalDP),
                "TRANSAKSI"     => array_sum($nominalTransaksi),
                "BAYAR_TUNAI"   => array_sum($nominalBayarTunai),
                "POTONGAN"      => array_sum($nominalPotongan),
                "KEMBALIAN"     => array_sum($nominalKembalian)
            ]
        ],200);
    }

    public function detailTransaksi($ID){
        $rekap  = DB::table('ud84_penjualan_rekap')->where('UNIQUE',$ID)->first();
        $detail = DB::table('ud84_penjualan_detail')->where('UNIQUE',$ID)->get();

        // Whether the line editor may be offered at all, and if not, why --
        // decided by Transaksi so the endpoint and this screen cannot disagree.
        [$dapatUbahItem, $alasanKoreksi] = Transaksi::syaratUbahItem($ID);

        return response()->json([
            "status"    => "success",
            "message"   => "Loaded",
            "data"      => [
                "rekap"     => $rekap,
                "detail"    => $detail,
                "KOREKSI"   => [
                    "DAPAT_UBAH_ITEM" => $dapatUbahItem,
                    "ALASAN"          => $alasanKoreksi,
                ]
            ]
        ],200);
    }

    public function getInvoices($ID){
        $dataRekap = DB::table('ud84_penjualan_rekap')->where('UNIQUE',$ID)->first();

        if (empty($dataRekap)) {
            return response()->json([
                "status"    => "error",
                "message"   => "Nota tidak ditemukan."
            ],200);
        }

        $dataDetail = DB::table('ud84_penjualan_detail')->where('UNIQUE',$ID)->get();
        $dataMember = DB::table('ud84_member')->where('NAMA', $dataRekap->NAMA)->first();

        $listDetail = [];
        $totalSum   = [];
        foreach($dataDetail as $data){
            // HARGA_TERJUAL is the line total, not a unit price. Rebuild the
            // unit price from the discount columns rather than dividing, so
            // HARGA * QUANTITY always reproduces JUMLAH exactly.
            $hargaSatuan = (int)$data->HARGA_ASLI - (int)$data->POTONGAN_PERSEN - (int)$data->POTONGAN_RUPIAH;

            $listDetail[] = [
                "QUANTITY"  => (int)$data->JUMLAH,
                "NAMA"      => $data->NAMA,
                "SATUAN"    => $data->SATUAN,
                "HARGA"     => $hargaSatuan,
                "JUMLAH"    => (int)$data->HARGA_TERJUAL
            ];
            $totalSum[] = (int)$data->HARGA_TERJUAL;
        }

        $totalBarang  = array_sum($totalSum);
        $potongan     = (int)($dataRekap->POTONGAN ?? 0);
        $totalTagihan = (int)($dataRekap->TOTAL ?? 0);
        $cash         = (int)($dataRekap->CASH ?? 0);
        $dp           = (int)($dataRekap->DP ?? 0);
        $dibayar      = $cash + $dp;

        return response()->json([
            "status" => "success",
            "message" => "Loaded",
            "data"  => [
                "tanggal"   => !empty($dataRekap->CREATED_AT) ? Carbon::parse($dataRekap->CREATED_AT)->translatedFormat('d F Y') : Carbon::now()->translatedFormat('d F Y'),
                "tuan"      => $dataRekap->NAMA ?? '-',
                "total"     => $totalBarang,
                "data"      => $listDetail,
                "ringkasan" => [
                    // rekap.TOTAL is already net of POTONGAN (see postPenjualan).
                    // Stored rekap.KEMBALIAN ignores DP and is deliberately unused.
                    "TOTAL_BARANG"  => $totalBarang,
                    "POTONGAN"      => $potongan,
                    "TOTAL_TAGIHAN" => $totalTagihan,
                    "CASH"          => $cash,
                    "DP"            => $dp,
                    "SISA"          => max(0, $totalTagihan - $dibayar),
                    "KEMBALIAN"     => max(0, $dibayar - $totalTagihan),
                ],
                // Surfaced at the top level so the layouts never have to read
                // the raw rekap row, whose KEMBALIAN and TOTAL are unsafe.
                "dibatalkan" => $dataRekap->STATUS === 'Dibatalkan',
                "rekap"     => $dataRekap,
                "alamat"    => empty($dataMember->ALAMAT) ? '-' : $dataMember->ALAMAT,
                "point"     => empty($dataMember->POINT) ? 0 : $dataMember->POINT
            ]
        ],200);
    }

    public function singleItemReport($ID){
        $dataDetail = DB::table('ud84_penjualan_detail as d')
            ->join('ud84_penjualan_rekap as r','r.UNIQUE','=','d.UNIQUE')
            ->where('r.STATUS','Aktif')
            ->where('d.NAMA',$ID)
            ->whereMonth('d.CREATED_AT',Carbon::now()->month)
            ->orderByDesc('d.ID')
            ->select('d.*')
            ->get();
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
        $getItems   = DB::table('ud84_penjualan_detail as d')
            ->join('ud84_penjualan_rekap as r','r.UNIQUE','=','d.UNIQUE')
            ->where('r.STATUS','Aktif')
            ->where('d.NAMA',$getItem->NAMA)
            ->whereBetween('d.CREATED_AT',[
                $request->input('START'),
                $request->input('FINISH'),
            ])
            ->select('d.*')
            ->get();

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
            "status"    => "success",
            "message"   => "Loaded",
            "data"      => [
                'data'                      => $listItem,
                'TOTAL_KOTOR'               => array_sum($totalKotor),
                'TOTAL_POTONGAN_RUPIAH'     => array_sum($potonganRupiah),
                'TOTAL_POTONGAN_PERSEN'     => array_sum($potonganPersen),
                'TOTAL_PIECES'              => array_sum($totalPieces)
            ]
        ],200);
    }

    public function updateDP(Request $request){
        $DB = DB::table('ud84_penjualan_rekap')->where('UNIQUE',$request->input('KODE'))->first();

        if (empty($DB)) {
            return response()->json([
                "status"    => "error",
                "message"   => "Transaksi tidak ditemukan."
            ],200);
        }

        if ($DB->STATUS === 'Dibatalkan') {
            return response()->json([
                "status"    => "error",
                "message"   => "Transaksi ini sudah dibatalkan, pelunasan DP tidak bisa diproses."
            ],200);
        }

        $nominalBaru = $request->input('DP') + $request->input('OLD_DP');
        DB::table('ud84_penjualan_rekap')->where('UNIQUE',$request->input('KODE'))->update([
            "DP"        => $nominalBaru,
            "KEMBALIAN" => empty($DB->CASH) ? 0 : $DB->KEMBALIAN + $nominalBaru
        ]);

        return response()->json([
            "status"    => "success",
            "message"   => "DP Updated!"
        ],200);
    }

    public function reportSales(Request $request) {
        try {
            $sales = $request->input('SALES');

            $startDate = $request->input('START');
            $endDate   = $request->input('END');

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

            $DB = DB::table('ud84_analisa_sales')->where('CREATED_AT', '>=', $startDate)->where('CREATED_AT', '<=', $endDate)->where('SALES', $sales)->get();

            return response()->json([
                "status"  => "success",
                "message" => "Data berhasil dimuat!.",
                "data" => $DB
            ], 200);
        } catch(\Throwable $e) {
            Log::info($e);
            return response()->json([
                "status"  => "error",
                "message" => "Ada kesalahan pada server"
            ], 400);
        }
    }

    public function salesMember(Request $request) {
        try {
            $sales = $request->input('SALES');

            $startDate = $request->input('START');
            $endDate   = $request->input('END');

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

            $DB = DB::table('ud84_member')->where('CREATED_BY', $sales)
            ->where('CREATED_AT', '>=', $startDate)->where('CREATED_AT', '<=', $endDate)->get();

            return response()->json([
                "status"    => "success",
                "message"   => "Data berhasil dimuat!.",
                "data"      => $DB
            ], 200);
        } catch(\Throwable $e) {
            Log::info($e);
            return response()->json([
                "status"  => "error",
                "message" => "Ada kesalahan pada server"
            ], 400);
        }
    }

}
