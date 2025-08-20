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
