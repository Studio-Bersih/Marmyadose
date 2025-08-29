<?php

namespace App\Http\Controllers\POS;

use Carbon\Carbon;
use App\DTO\Responses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class Transaksi extends Controller
{
    public function transaksiPenjualan(Request $request): JsonResponse {
        $staff = $request->input('staff');
        $start = $request->input('start', now()->toDateString());
        $end   = $request->input('end', now()->toDateString());

        $DB = DB::table('pos_penjualan_detail')
            ->where('TOKEN', $staff)
            ->whereBetween('CREATED_AT', [
                Carbon::parse($start)->startOfDay(),
                Carbon::parse($end)->endOfDay()
            ])
            ->get();

        $data = [];
        foreach($DB as $DB) {
            $originalPrice = DB::table('pos_master_produk')
                ->where('ID', $DB->KODE)
                ->first('HARGA_STOK');

            $data[] = [
                "ID"                => $DB->ID,
                "NAMA"              => $DB->NAMA,
                "TERJUAL"           => $DB->JUMLAH,
                "SISA_STOK"         => $DB->SISA_STOK,
                "HARGA_BELI"        => $originalPrice->HARGA_STOK,
                "HARGA_JUAL"        => $DB->HARGA_JUAL,
                "TOTAL_TRANSAKSI"   => $DB->JUMLAH * $DB->HARGA_JUAL,
                "WAKTU_TRANSAKSI"   => date("d/m/Y H:i", strtotime($DB->CREATED_AT)),
            ];
        }

        // --- Tambahan: total emoney summary by COUNTER ---
        $totalIncrease = DB::table('pos_rekap_emoney')->where('TOKEN', $staff)->where('COUNTER', 'Increase')->whereDate('CREATED_AT', DB::raw('CURDATE()'))->sum('AMOUNT');
        $totalDecrease = DB::table('pos_rekap_emoney')->where('TOKEN', $staff)->where('COUNTER', 'Decrease')->whereDate('CREATED_AT', DB::raw('CURDATE()'))->sum('AMOUNT');
        $totalFee = DB::table('pos_rekap_emoney')->where('TOKEN', $staff)->whereDate('CREATED_AT', DB::raw('CURDATE()'))->sum('FEE');

        return response()->json(new Responses( "success", 'Data berhasil dimuat!',
            [
                'items'          => $data,
                'increase_total' => $totalIncrease,
                'decrease_total' => $totalDecrease,
                'fee_total'      => $totalFee,
            ]
        ));
    }

    public function deleteDetailPenjualan(Request $request): JsonResponse{
        $id = $request->input('id');
        $usaha = $request->input('usaha');
        $token = $request->input('token');

        $getDetail = DB::table('pos_penjualan_detail')->where('ID', $id)->first();

        DB::table('pos_log')->insert([
            "USAHA" => $usaha,
            "TOKEN" => $token,
            "TEXT"  => $token . " menghapus : " . $getDetail->NAMA . " sejumlah " . $getDetail->JUMLAH . " dari transaksi penjualan.",
        ]);

        DB::table('pos_penjualan_detail')->where('ID', $id)->delete();
        return response()->json(new Responses(
            "success", "Item berhasil dihapus!"
        ), 200);
    }

    public function trackKeuntungan(Request $request): JsonResponse {
        $staff = $request->input('staff');
        $searchDate = $request->input('searchDate');

        $query = DB::table('pos_penjualan_detail')->where('TOKEN', $staff);

        if ($searchDate) {
            $query->whereDate('CREATED_AT', $searchDate);
        } else {
            $query->whereDate('CREATED_AT', DB::raw('CURDATE()'));
        }

        $details = $query->get();

        $result = [];
        $totalHargaBeli = 0;
        $totalHargaJual = 0;
        $totalKeuntungan = 0;

        foreach ($details as $detail) {
            $produk = DB::table('pos_master_produk')->where('ID', $detail->KODE)->first(['HARGA_STOK']);
            $hargaBeli = $produk ? $produk->HARGA_STOK : 0;
            $hargaJual = $detail->HARGA_JUAL;
            $jumlah = $detail->JUMLAH;
            $keuntungan = ($hargaJual - $hargaBeli) * $jumlah;

            $result[] = [
                'ID' => $detail->ID,
                'NAMA' => $detail->NAMA,
                'HARGA_BELI' => $hargaBeli,
                'HARGA_JUAL' => $hargaJual,
                'JUMLAH' => $jumlah,
                'KEUNTUNGAN_BERSIH' => $keuntungan,
            ];

            $totalHargaBeli += $hargaBeli * $jumlah;
            $totalHargaJual += $hargaJual * $jumlah;
            $totalKeuntungan += $keuntungan;
        }

        $summary = [
            'TOTAL_HARGA_BELI' => $totalHargaBeli,
            'TOTAL_HARGA_JUAL' => $totalHargaJual,
            'TOTAL_KEUNTUNGAN_BERSIH' => $totalKeuntungan,
        ];

        if (empty($result)) {
            return response()->json(new Responses(
                "success",
                "Tidak ada data keuntungan ditemukan.",
                [
                    'items' => [],
                    'summary' => $summary
                ]
            ));
        }

        return response()->json(new Responses(
            "success",
            "Data keuntungan berhasil dimuat!",
            [
                'items' => $result,
                'summary' => $summary
            ]
        ));
    }

    public function getLogs(Request $request) {
        $usaha = $request->input('usaha');
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $query = DB::table('pos_log')->where('USAHA', $usaha);

        if ($startDate && $endDate) {
            try {
                $start = Carbon::parse($startDate)->startOfDay(); // 00:00:00
                $end = Carbon::parse($endDate)->endOfDay();       // 23:59:59

                $query->whereBetween('CREATED_AT', [$start, $end]);
            } catch (\Exception $e) {
                return response()->json(new Responses(
                    "error", "Tanggal yang dikirim tidak valid.", null
                ));
            }
        }

        $logs = $query->orderBy('CREATED_AT', 'desc')->get();

        return response()->json(new Responses(
            "success",
            $logs->isEmpty() ? "Tidak ada log ditemukan untuk tanggal tersebut." : "Logs berhasil dimuat!",
            $logs
        ));
    }

    public function updateDate(Request $request) {
        $id = $request->input('ID');
        $createdAt = $request->input('CREATED_AT');

        if (!$id || !$createdAt) {
            return response()->json(new Responses(
                "error", "ID dan CREATED_AT harus diisi.", null
            ), 400);
        }

        $updated = DB::table('pos_penjualan_detail')->where('ID', $id)->update([
            "CREATED_AT" => $createdAt
        ]);

        if ($updated) {
            return response()->json(new Responses(
                "success", "Tanggal berhasil diupdate!"
            ), 200);
        } else {
            return response()->json(new Responses(
                "error", "Tanggal gagal diupdate.", null
            ), 404);
        }
    }

}