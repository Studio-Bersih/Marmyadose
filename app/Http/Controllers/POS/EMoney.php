<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Log;

class EMoney extends Controller
{
    public function getRanged(Request $request) {
        $usaha = $request->input('USAHA');
        $DB = DB::table('pos_payment_range_types')->where('USAHA', $usaha)->get();

        return response()->json([
            "status"    => "success",
            "message"   => "Item loaded",
            "data"      => $DB
        ]);
    }
    
    public function getRangedTypes(Request $request) {
        $usaha = $request->input('USAHA');
        $DB = DB::table('pos_payment_ranges')->where('USAHA', $usaha)->get();

        return response()->json([
            "status"    => "success",
            "message"   => "Item loaded",
            "data"      => $DB
        ]);
    }

    public function insertMoney(Request $request) {
        $type   = $request->input('TYPE');   // e.g. "Tarik Tunai"
        $staff  = $request->input('TOKEN');
        $amount = (int) $request->input('AMOUNT');
        $usaha  = $request->input('USAHA');

        try {
            // 1. Find type info (for COUNTER and TYPE_ID relation)
            $findType = DB::table('pos_payment_range_types')
                ->where('NAME', $type)
                ->where('USAHA', $usaha)
                ->first(['ID', 'COUNTER']);

            if (!$findType) {
                return response()->json([
                    "status"  => "error",
                    "message" => "Jenis transaksi tidak ditemukan!",
                    "data"    => null
                ], 404);
            }

            // 2. Find the fee based on range
            $findRange = DB::table('pos_payment_ranges')
                ->where('TYPE_ID', $findType->ID)
                ->where('USAHA', $usaha)
                ->where('RANGE_START', '<=', $amount)
                ->where('RANGE_END', '>=', $amount)
                ->first(['FEE']);

            $fee = $findRange ? (int) $findRange->FEE : 0;

            // 3. Prepare data for insert
            $data = [
                'TYPE'   => $type,
                'AMOUNT' => $amount,
                'FEE'    => $fee,
                'TOKEN'  => $staff,
                'USAHA'  => $usaha,
            ];

            if (!empty($findType->COUNTER)) {
                $data['COUNTER'] = $findType->COUNTER;
            }

            // 4. Transaction wrap
            DB::beginTransaction();
                DB::table('pos_rekap_emoney')->insert($data);
            DB::commit();

            return response()->json([
                "status"  => "success",
                "message" => "Transaksi berhasil disimpan!",
                "data"    => $fee
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                "status"  => "error",
                "message" => "Transaksi gagal disimpan! " . $e->getMessage(),
                "data"    => null
            ], 500);
        }
    }


    public function addType(Request $request){
        DB::table('pos_payment_range_types')->insert([
            'NAME' => $request->input('name'),
            'USAHA' => $request->input('usaha'),
            'COUNTER' => $request->input('type'),
            'CREATED_AT' => now(),
            'UPDATED_AT' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe berhasil ditambahkan'
        ]);
    }

    public function addRange(Request $request){
        $usaha = DB::table('pos_payment_range_types')
            ->where('ID', $request->type_id)
            ->value('USAHA');

        if (!$usaha) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tipe tidak valid atau usaha tidak ditemukan'
            ]);
        }

        DB::table('pos_payment_ranges')->insert([
            'TYPE_ID' => $request->type_id,
            'USAHA' => $usaha,
            'RANGE_START' => $request->range_start,
            'RANGE_END' => $request->range_end,
            'FEE' => $request->fee,
            'CREATED_AT' => now(),
            'UPDATED_AT' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Range berhasil ditambahkan'
        ]);
    }

    public function viewRange(Request $request){
        $DB = DB::table('pos_payment_ranges')->where('ID', $request->id)->first(['ID', 'RANGE_START', 'RANGE_END', 'FEE']);

        return response()->json([
            'status'    => 'success',
            'message'   => 'Range berhasil dimuat',
            'data'      => $DB
        ]);
    }

    public function updateRange(Request $request){
        DB::table('pos_payment_ranges')
            ->where('ID', $request->input('ID'))
            ->update([
                "RANGE_START"   => $request->input('START'),
                "RANGE_END"     => $request->input('END'),
                "FEE"           => $request->input('FEE'),
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Range berhasil diupdate'
        ]);
    }

    public function deleteRange(Request $request){
        DB::table('pos_payment_ranges')
            ->where('ID', $request->id)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Range berhasil dihapus'
        ]);
    }

    public function editMainRange(Request $request) {
        DB::table('pos_payment_range_types')->where('ID', $request->input('ID'))->update([
            "NAME" => $request->input('NAME'),
            "COUNTER" => $request->input('COUNTER')
        ]);

        return response()->json([
            "status" => "success",
            "message" => "Kategori utama berhasil diupdate!"
        ]);
    }

    public function deleteMainRange(Request $request) {
        DB::table('pos_payment_ranges')->where('TYPE_ID', $request->input('ID'))->delete();
        DB::table('pos_payment_range_types')->where('ID', $request->input('ID'))->delete();

        return response()->json([
            "status" => "success",
            "message" => "Kategori utama berhasil dihapus!"
        ]);
    }
    
    public function deleteTransaction(Request $request) {
        $id = $request->input('id');

        if (!$id) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID transaksi tidak valid atau tidak ditemukan!',
                'data' => null
            ]);
        }

        try {
            $deleted = DB::table('pos_rekap_emoney')
                ->where('ID', $id)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Transaksi berhasil dihapus.',
                    'data' => null
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaksi tidak ditemukan!',
                    'data' => null
                ]);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus transaksi: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function updateTransactionDate(Request $request) {
        $id = $request->input('id');
        $new_date = $request->input('new_date');

        if (!$id || !$new_date) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak lengkap (ID atau Tanggal baru kosong)!',
                'data' => null
            ]);
        }

        try {
            $getData = DB::table('pos_rekap_emoney')->where('ID', $id)->first();
            if (!$getData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaksi tidak ditemukan!',
                    'data' => null
                ]);
            }

            // Keep current time from the DB or fallback to 00:00:00
            $time = '00:00:00';
            if (!empty($getData->CREATED_AT)) {
                $time = date('H:i:s', strtotime($getData->CREATED_AT));
            }
            $finalDateTime = $new_date . ' ' . $time;

            DB::table('pos_rekap_emoney')->where('ID', $id)->update([
                'CREATED_AT' => $finalDateTime
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Tanggal transaksi berhasil diubah.',
                'data' => null
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengubah tanggal transaksi: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

}
