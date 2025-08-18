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
            'message' => 'Berhasil memuat data',
            'data' => $data
        ]);
    }

   public function getMonthlySalesReport(Request $request){
        $usaha = $request->input('usaha');
        $start = $request->input('start_date');
        $end   = $request->input('end_date');
        $type  = $request->input('type');

        if (!$usaha) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Parameter usaha wajib diisi.'
            ]);
        }

        // Ambil semua TOKEN user dari usaha ini
        $tokens = DB::table('pos_users')
            ->where('USAHA', $usaha)
            ->pluck('TOKEN');

        if ($tokens->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada pengguna terdaftar untuk usaha ini.'
            ]);
        }

        // Query data harian
        $query = DB::table('pos_penjualan_detail')
            ->join('pos_penjualan_rekap', 'pos_penjualan_detail.KEYS', '=', 'pos_penjualan_rekap.KEYS')
            ->select(
                DB::raw('DATE(pos_penjualan_detail.CREATED_AT) as tanggal'),
                DB::raw('COUNT(DISTINCT pos_penjualan_rekap.ID) as jumlah_transaksi'),
                DB::raw('SUM(pos_penjualan_rekap.CASH) as total_cash'),
                DB::raw('SUM(pos_penjualan_rekap.KEMBALI) as total_kembali'),
                DB::raw('SUM(pos_penjualan_rekap.TOTAL_TRANSAKSI) as total_transaksi'),
                DB::raw('SUM(pos_penjualan_detail.JUMLAH * pos_penjualan_detail.HARGA_JUAL) as total_penjualan'),
                DB::raw('SUM((pos_penjualan_detail.HARGA_JUAL * pos_penjualan_detail.JUMLAH) - (pos_penjualan_detail.HARGA_STOK * pos_penjualan_detail.JUMLAH)) as total_net')
            )
            ->whereIn('pos_penjualan_rekap.TOKEN', $tokens);

        if ($start && $end) {
            $query->whereBetween('pos_penjualan_detail.CREATED_AT', [
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ]);
        }

        // Filter berdasarkan cabang
        if ($type === "Semua") {
            $filteredTokens = $tokens;

        } else if ($type === "Cabang 1") {
            $cabangTokens = DB::table('pos_users')
                ->where('USAHA', $usaha)
                ->where('CABANG', '1')
                ->pluck('TOKEN');

            if ($cabangTokens->isEmpty()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Tidak ada pengguna terdaftar di Cabang 1 untuk usaha ini.'
                ]);
            }

            $filteredTokens = $cabangTokens;

        } else if ($type === "Cabang 2") {
            $cabangTokens = DB::table('pos_users')
                ->where('USAHA', $usaha)
                ->where('CABANG', '2')
                ->pluck('TOKEN');

            if ($cabangTokens->isEmpty()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Tidak ada pengguna terdaftar di Cabang 2 untuk usaha ini.'
                ]);
            }

            $filteredTokens = $cabangTokens;

        } else if ($type === "Cabang 3") {
            $cabangTokens = DB::table('pos_users')
                ->where('USAHA', $usaha)
                ->where('CABANG', '3')
                ->pluck('TOKEN');

            if ($cabangTokens->isEmpty()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Tidak ada pengguna terdaftar di Cabang 3 untuk usaha ini.'
                ]);
            }

            $filteredTokens = $cabangTokens;

        } else {
            // Cek apakah type itu TOKEN user (Per PIC)
            $isUserToken = DB::table('pos_users')
                ->where('USAHA', $usaha)
                ->where('TOKEN', $type)
                ->exists();

            if (!$isUserToken) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Jenis laporan tidak dikenali atau token user tidak valid.'
                ]);
            }

            // Jika token valid, filter langsung ke token user itu
            $filteredTokens = collect([$type]);
        }

        // Apply tokens ke query utama
        $query->whereIn('pos_penjualan_rekap.TOKEN', $filteredTokens);

        $dailyData = $query
            ->groupBy(DB::raw('DATE(pos_penjualan_detail.CREATED_AT)'))
            ->orderBy('tanggal', 'asc')
            ->get();

        // Query grand total
        $grandTotal = DB::table('pos_penjualan_detail')
            ->join('pos_penjualan_rekap', 'pos_penjualan_detail.KEYS', '=', 'pos_penjualan_rekap.KEYS')
            ->select(
                DB::raw('SUM(pos_penjualan_detail.JUMLAH * pos_penjualan_detail.HARGA_JUAL) as total_penjualan'),
                DB::raw('SUM((pos_penjualan_detail.HARGA_JUAL * pos_penjualan_detail.JUMLAH) - (pos_penjualan_detail.HARGA_STOK * pos_penjualan_detail.JUMLAH)) as total_net')
            )
            ->whereIn('pos_penjualan_rekap.TOKEN', $filteredTokens);

        if ($start && $end) {
            $grandTotal->whereBetween('pos_penjualan_detail.CREATED_AT', [
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ]);
        }

        $totals = $grandTotal->first();

        $allBranchTotals = [];

        foreach (['1', '2', '3'] as $cabang) {
            $cabangTokens = DB::table('pos_users')
                ->where('USAHA', $usaha)
                ->where('CABANG', $cabang)
                ->pluck('TOKEN');

            $cabangQuery = DB::table('pos_penjualan_detail')
                ->join('pos_penjualan_rekap', 'pos_penjualan_detail.KEYS', '=', 'pos_penjualan_rekap.KEYS')
                ->select(
                    DB::raw('COUNT(DISTINCT pos_penjualan_rekap.ID) as total_transaction'),
                    DB::raw('SUM((pos_penjualan_detail.HARGA_JUAL - pos_penjualan_detail.HARGA_STOK) * pos_penjualan_detail.JUMLAH) as total_net')
                )
                ->whereIn('pos_penjualan_rekap.TOKEN', $cabangTokens);


            if ($start && $end) {
                $cabangQuery->whereBetween('pos_penjualan_detail.CREATED_AT', [
                    $start . ' 00:00:00',
                    $end . ' 23:59:59'
                ]);
            }

            $result = $cabangQuery->first();

            $allBranchTotals["CABANG_{$cabang}"] = [
                "TOTAL_TRANSACTION" => $result->total_transaction ?? 0,
                "TOTAL_AMOUNT"      => $result->total_amount ?? 0,
                "TOTAL_NET"         => $result->total_net ?? 0,
            ];
        }


        // Return response
        return response()->json([
            'status'  => 'success',
            'message' => 'Berhasil memuat data',
            'data'    => [
                'daily'  => $dailyData,
                'totals' => $totals,
                'total_transactions_all_branch' => $allBranchTotals
            ]
        ]);
    }

}
