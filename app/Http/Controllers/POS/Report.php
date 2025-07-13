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

            $DB = DB::table('pos_penjualan_detail')->where('TOKEN',$pic)->whereDate('created_at', '=' , $date)->get();

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
                    "Waktu Transaksi"   => date('d/m/Y H:i', strtotime($DB->CREATED_AT)),
                ];
            }

            return response()->json(new Responses(
                "success","Data berhasil dimuat!", $data
            ));
        }

        return response()->json(new Responses(
            "error","Tidak ada data!!", $data
        ));
    }

    public function getEmoneyReport(Request $request){
        $usaha = $request->input('usaha');
        $start = $request->input('start_date');
        $end = $request->input('end_date');

        $query = DB::table('pos_rekap_emoney_report')->where('USAHA', $usaha);

        if ($start && $end) {
            $query->where('CREATED_AT', '>=', $start)->where('CREATED_AT', '<=', $end);
        }

        $data = $query
            ->orderBy('CREATED_AT', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function getMonthlySalesReport(Request $request){
        $usaha = $request->input('usaha');
        $start = $request->input('start_date');
        $end = $request->input('end_date');

        if (!$usaha) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter usaha wajib diisi.'
            ]);
        }

        $tokens = DB::table('pos_users')
            ->where('USAHA', $usaha)
            ->pluck('TOKEN');

        if ($tokens->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada pengguna terdaftar untuk usaha ini.'
            ]);
        }

        $query = DB::table('pos_penjualan_rekap')
            ->select(
                DB::raw('DATE(CREATED_AT) as tanggal'),
                DB::raw('COUNT(*) as jumlah_transaksi'),
                DB::raw('SUM(CASH) as total_cash'),
                DB::raw('SUM(KEMBALI) as total_kembali'),
                DB::raw('SUM(TOTAL_TRANSAKSI) as total_transaksi')
            )
            ->whereIn('TOKEN', $tokens);

        if ($start && $end) {
            $query->where('CREATED_AT', '>=', $start)->where('CREATED_AT', '<=', $end);
        }

        $dailyData = $query
            ->groupBy(DB::raw('DATE(CREATED_AT)'))
            ->orderBy('tanggal', 'asc')
            ->get();

        $grandTotal = DB::table('pos_penjualan_rekap')
            ->select(
                DB::raw('SUM(CASH) as total_cash'),
                DB::raw('SUM(KEMBALI) as total_kembali'),
                DB::raw('SUM(TOTAL_TRANSAKSI) as total_transaksi')
            )
            ->whereIn('TOKEN', $tokens);

        if ($start && $end) {
            $grandTotal->where('CREATED_AT', '>=', $start)->where('CREATED_AT', '<=', $end);
        }

        $totals = $grandTotal->first();

        return response()->json([
            'status'    => 'success',
            'message'   => 'Berhasil memuat data',
            'data' => [
                'daily'     => $dailyData,
                'totals'    => $totals
            ]
        ]);
    }
}
