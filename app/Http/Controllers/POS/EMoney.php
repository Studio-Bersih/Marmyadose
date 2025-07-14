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
        $type   = $request->input('TYPE');
        $staff  = $request->input('TOKEN');
        $amount = $request->input('AMOUNT');
        $usaha  = $request->input('USAHA');
        $fee    = $request->input('FEE');

        try {
            // Find the counter behavior for the selected type
            $findType = DB::table('pos_payment_range_types')->where('NAME', $type)->first(['COUNTER']);

            // Prepare data to insert
            $data = [
                'TYPE'   => $type,
                'AMOUNT' => $amount,
                'FEE'    => $fee,
                'TOKEN'  => $staff,
                'USAHA'  => $usaha,
            ];

            // If counter logic exists, add it to the insert
            if (!empty($findType)) {
                $data['COUNTER'] = $findType->COUNTER; // Either 'Increment' or 'Decrease'
            }

            // Transaction wrap
            DB::beginTransaction();
                DB::table('pos_rekap_emoney')->insert($data);
            DB::commit();

            return response()->json([
                "status"  => "success",
                "message" => "Transaksi berhasil disimpan!",
                "data"    => null
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            // You can log error here if needed
            // Log::error("eMoney Transaction Failed", ['error' => $e->getMessage()]);

            return response()->json([
                "status"  => "error",
                "message" => "Transaksi gagal disimpan!",
                "data"    => null
            ]);
        }
    }

    public function addType(Request $request){
        DB::table('pos_payment_range_types')->insert([
            'NAME' => $request->input('name'),
            'USAHA' => $request->input('usaha'),
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

    public function deleteRange(Request $request){
        DB::table('pos_payment_ranges')
            ->where('ID', $request->id)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Range berhasil dihapus'
        ]);
    }

}
