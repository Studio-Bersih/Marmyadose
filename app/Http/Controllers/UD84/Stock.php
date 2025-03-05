<?php

namespace App\Http\Controllers\UD84;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Log;
use DB;

class Stock extends Controller {
    public function getUser(){
        $DB = DB::table('ud84_sales')->get();
        return response()->json([
            "status"    => "success",
            "message"   => "Loaded",
            "data"      => $DB
        ],200);
    }

    public function dashboard(Request $request) {
        try {
            $keyWords  = $request->input('searchBar');
            $startDate = $request->input('start');
            $endDate   = $request->input('end');
            $tipe      = $request->input('tipe'); // 'Item Masuk' or 'Item Keluar'

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

            // Query Data dari Database
            $query = DB::table('ud84_logistics')->where('TIPE', $tipe)->where('CREATED_AT', '>=', $startDate)->where('CREATED_AT', '<=', $endDate);

            // Filter berdasarkan ID jika ada kata kunci
            if (!empty($keyWords)) {
                $query->where('ID', $keyWords);
            }

            // Ambil data & format hasil
            $data = $query->get(['ID', 'KETERANGAN', 'CREATED_AT'])
                ->map(fn ($item) => [
                    "ID"           => $item->ID,
                    "NO_TRANSAKSI" => ($tipe === "Item Masuk" ? "LOG/IM/" : "LOG/IK/") . $item->ID,
                    "KETERANGAN"   => $item->KETERANGAN,
                    "CREATED_AT"   => $item->CREATED_AT
                ]);

            return response()->json([
                "status"  => "success",
                "message" => "Berhasil dimuat.",
                "data"    => $data
            ], 200);

        } catch (\Exception $e) {
            Log::info($e);
            return response()->json([
                "status"  => "error",
                "message" => "Terjadi kesalahan, silakan coba lagi."
            ], 500);
        }
    }

    public function view(Request $request) {
        try {
            $logisticsId = $request->input('ID');
    
            $logisticsRecord = DB::table('ud84_logistics')->where('ID', $logisticsId)->first();
            
            if (!$logisticsRecord) {
                return response()->json([
                    "status"  => "error",
                    "message" => "Logistics record not found"
                ], 404);
            }
    
            $logisticsType = $logisticsRecord->TIPE;
            
            $logisticsDetails = DB::table('ud84_logistics_detail')->where('KEY', $logisticsRecord->KEY)->get();
    
            $cartItems = [];
            foreach ($logisticsDetails as $detail) {
                $cartItems[] = [
                    "NAMA"       => $detail->NAMA,
                    "STOK"       => $detail->STOK,
                    "TIPE"       => $detail->TIPE,
                    "CREATED_AT" => $detail->CREATED_AT
                ];
            }
    
            $responseData = [
                "TIPE"  => $logisticsType,
                "CARTS" => $cartItems,
                "NOTES" => $logisticsRecord->KETERANGAN
            ];
    
            if ($logisticsType === "Item Keluar") {
                $responsiblePersons = DB::table('ud84_afkir_responsible')->where('KEY', $logisticsRecord->KEY)->get();
                
                $personInChargeList = [];
                foreach ($responsiblePersons as $person) {
                    $personInChargeList[] = [
                        "NAMA"       => $person->NAMA,
                        "NOMINAL"    => $person->NOMINAL,
                        "CREATED_AT" => $person->CREATED_AT
                    ];
                }
    
                $responseData['PIC'] = $personInChargeList;
            }
    
            return response()->json([
                "status"  => "success",
                "message" => "Data retrieved successfully",
                "data"    => $responseData
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "status"  => "error",
                "message" => "An error occurred: " . $e->getMessage()
            ], 500);
        }
    }
    
    public function stocksAdmin(Request $request){
        $tipe = $request->input('tipe');
        $catatan = $request->input('catatan');
        $penanggungJawab = $request->input('penanggungJawabAfkir');
        $carts = $request->input('cart');
        $key = uniqid();

        DB::beginTransaction();

        try {
            // Insert responsible persons if "Item Keluar"
            if ($tipe === "Item Keluar" && !empty($penanggungJawab)) {
                $setAfkir = array_map(fn($pj) => [
                    "KEY"     => $key,
                    "NAMA"    => $pj['NAMA'],
                    "USER"    => $pj['ID'],
                    "NOMINAL" => preg_replace('/\D/', '', $pj['NOMINAL'])
                ], $penanggungJawab);

                DB::table('ud84_afkir_responsible')->insert($setAfkir);
            }

            // Insert main logistics entry
            DB::table('ud84_logistics')->insert([
                "TIPE"       => $tipe,
                "KEY"        => $key,
                "KETERANGAN" => $catatan,
                "CREATED_AT" => now()
            ]);

            // Prepare stock update, logging, and transaction logs
            $stocksUpdate = [];
            $logEntries = [];

            foreach ($carts as $cart) {
                // Ambil stok sebelumnya
                $previousStock = DB::table('ud84_master_produk')
                    ->where('ID', $cart['ID'])
                    ->value('STOK'); // Menggunakan value() agar langsung mendapatkan nilai integer
            
                // Hitung stok akhir
                $finalStock = ($tipe === "Item Keluar")
                    ? $previousStock - $cart['INPUT_STOK']
                    : $previousStock + $cart['INPUT_STOK'];
            
                // Update stok di database
                DB::table('ud84_master_produk')
                    ->where('ID', $cart['ID'])
                    ->update([
                        'STOK' => $finalStock
                    ]);
            
                // Prepare log data
                $stocksUpdate[] = [
                    "KODE" => $cart['ID'],
                    "NAMA" => $cart['NAMA'],
                    "STOK" => $cart['INPUT_STOK'],
                    "TIPE" => $tipe,
                    "KEY"  => $key
                ];
            
                // Prepare transaction logs for history (no updates, only inserts)
                $logEntries[] = [
                    "KODE_ITEM"  => $cart['ID'],
                    "NAMA_ITEM"  => $cart['NAMA'],
                    "ASAL"       => $tipe,
                    "MASUK"      => $tipe === "Item Masuk" ? $cart['INPUT_STOK'] : 0,
                    "KELUAR"     => $tipe === "Item Keluar" ? $cart['INPUT_STOK'] : 0,
                    "STOK_FINAL" => $finalStock,
                    "CREATED_AT" => now()
                ];
            }

            // Batch insert for stock logs
            if (!empty($stocksUpdate)) {
                DB::table('ud84_logistics_detail')->insert($stocksUpdate);
            }

            // Batch insert for transaction logs (history)
            if (!empty($logEntries)) {
                DB::table('ud84_logs')->insert($logEntries);
            }

            DB::commit();

            return response()->json([
                "status"  => "success",
                "message" => "Data tersimpan"
            ], 200);
        } catch (\Exception $e) {
            Log::info($e);
            DB::rollBack();
            return response()->json([
                "status"  => "error",
                "message" => "Terjadi kesalahan.",
                "error"   => $e->getMessage()
            ], 500);
        }
    }

    public function kartuStok(Request $request) {
        try {
            $kodeItem = $request->input('searchBar');
            $start = $request->input('startDate');
            $end = $request->input('endDate');
        
            // Validate required parameters
            if (!$kodeItem || !$start || !$end) {
                return response()->json([
                    "status" => "error",
                    "message" => "Missing required parameters."
                ], 400);
            }
        
            // Fetch data
            $DB = DB::table('ud84_logs')
                ->where('KODE_ITEM', $kodeItem)
                ->where('CREATED_AT', '>=', $start)
                ->where('CREATED_AT', '<=', $end)
                ->get(['NAMA_ITEM', 'ASAL', 'MASUK', 'KELUAR', 'CREATED_AT','STOK_FINAL'])
                ->map(fn ($item) => [
                    "NAMA"       => $item->NAMA_ITEM,
                    "ASAL"       => $item->ASAL,
                    "MASUK"      => $item->MASUK,
                    "KELUAR"     => $item->KELUAR,
                    "STOK"       => $item->STOK_FINAL,
                    "CREATED_AT" => $item->CREATED_AT,
                ]);

            Log::info($DB);
        
            return response()->json([
                "status"  => "success",
                "message" => "Loaded",
                "data"    => $DB
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                "status"  => "error",
                "message" => "Database query error: " . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                "status"  => "error",
                "message" => "An unexpected error occurred: " . $e->getMessage()
            ], 500);
        }
    }
}
