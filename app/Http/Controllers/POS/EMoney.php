<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

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
        DB::table('pos_rekap_emoney')->insert([
            'TYPE'      => $request->input('TYPE'),
            'AMOUNT'    => $request->input('AMOUNT'),
            'FEE'       => $request->input('FEE'),
            'TOKEN'     => $request->input('TOKEN'),
            'USAHA'     => $request->input('USAHA'),
        ]);

        return response()->json([
            "status"    => "success",
            "message"   => "Transaksi berhasil disimpan!",
            "data"      => null
        ]);
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
