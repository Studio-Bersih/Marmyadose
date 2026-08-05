<?php

namespace App\Http\Controllers\UD84;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Log;
use DB;

class Pesanan extends Controller
{
    public function postPesanan(Request $request) {
        DB::beginTransaction();

        try {
            $unique = uniqid();
            $nama = $request->input('NAMA');
            $whatsApp = $request->input('WHATSAPP');
            $sales = $request->input('SALES');
            $carts = $request->input('CARTS');
            $notes = $request->input('NOTES');

            if(empty($carts)) {
                return response()->json([
                    "status"    => "error",
                    "message"   => "Tidak ada item di keranjang"
                ],200);
            }

            DB::table('ud84_pesanan_rekap')->insert([
                "NAMA"      => $nama,
                "WHATSAPP"  => $whatsApp,
                "SALES"     => $sales,
                "KODE"      => $unique,
                "CATATAN"   => $notes,
            ]);

            $useCarts = [];
            for($i = 0; $i < count($carts); $i++) {
                $useCarts[] = [
                    "KODE"      => $unique,
                    "KODE_ITEM" => $carts[$i]['ID'],
                    "JUMLAH"    => $carts[$i]['QUANTITY'],
                ];
            }

            DB::table('ud84_pesanan_detail')->insert($useCarts);

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Pesanan tersimpan!',
            ],200);
        } catch (\Throwable $e) {
            DB::rollback();
            Log::info($e);
            return response()->json([
                'status'    => 'error',
                'message'   => 'Ada kesalahan pada server.',
            ], 200);
        }
    }

    public function getPesanan(Request $request) {
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

        $DB = DB::table('ud84_pesanan_rekap')->where('CREATED_AT', '>=', $startDate)->where('CREATED_AT', '<=', $endDate)->orderByDesc('ID')->get();

        $useDB = [];
        foreach($DB as $DB) {
            // SALES is null for orders placed without a salesperson ("Tanpa
            // Sales" on the order form), and the row is gone if a salesperson
            // was ever deleted. Either way there is no name to show.
            $salesName = DB::table('ud84_sales')->where('ID', $DB->SALES)->first(['NAMA']);
            $useDB[] = [
                "NAMA"          => $DB->NAMA,
                "WHATSAPP"      => $DB->WHATSAPP,
                "SALES"         => $salesName->NAMA ?? '-',
                "CATATAN"       => $DB->CATATAN,
                "KODE"          => $DB->KODE,
                "VALID"         => $DB->VALID,
                "CREATED_AT"    => $DB->CREATED_AT
            ];
        }

        return response()->json([
            'status'    => 'success',
            'message'   => 'Loaded',
            'data'      => $useDB
        ],200);
    }

    public function getItems(Request $request) {
        $id = $request->input("ID");

        try {
            $DB = DB::table('ud84_pesanan_detail')->where('KODE', $id)->get(['KODE_ITEM', 'JUMLAH']);

            $useCarts = [];
            foreach($DB as $DB) {
                $findItem = DB::table('ud84_master_produk')->where('ID', $DB->KODE_ITEM)->first();
                $useCarts[] = [
                    "NAMA"              => $findItem->NAMA,
                    "JUMLAH"            => $DB->JUMLAH,
                    "STOK"              => $findItem->STOK,
                    "SATUAN"            => $findItem->TIPE,
                    "HARGA_PER_ITEM"    => $findItem->HARGA_PER_ITEM,
                    "HARGA_JUAL"        => $findItem->HARGA_JUAL,
                    "DISTRIBUTOR"       => $findItem->DISTRIBUTOR
                ];
            }

            return response()->json([
                'status'    => 'success',
                'message'   => 'Loaded',
                'data'      => $useCarts
            ],200);
        } catch(\Throwable $e) {
            Log::info($e);
            return response()->json([
                "status"  => "error",
                "message" => "Ada kesalahan pada server."
            ], 500);
        }
    }

    public function removeItem(Request $request){
        $id = $request->input('ID');
        DB::table('ud84_pesanan_rekap')->where('KODE', $id)->delete();
        DB::table('ud84_pesanan_detail')->where('KODE', $id)->delete();
        return response()->json([
            "status"  => "success",
            "message" => "Pesanan berhasil dihapus."
        ], 200);
    }

    public function validateItem(Request $request){
        $id = $request->input('ID');
        DB::table('ud84_pesanan_rekap')->where('KODE', $id)->update([
            "VALID" => "Verified"
        ]);
        return response()->json([
            "status"  => "success",
            "message" => "Pesanan berhasil dihapus."
        ], 200);
    }

    public function salesHistory(Request $request) {
        try {
            $id = $request->input('ID');
            Log::info($id);
            $DB = DB::table('ud84_pesanan_rekap')
                ->where('SALES', $id)
                ->where('VALID', NULL)
                ->get(['KODE', 'CATATAN']);
    
            return response()->json([
                "status"  => "success",
                "message" => "Data berhasil dimuat!",
                "data"    => $DB
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"  => "error",
                "message" => "Terjadi kesalahan: " . $e->getMessage()
            ], 500);
        }
    }
}
