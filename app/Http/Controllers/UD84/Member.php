<?php

namespace App\Http\Controllers\UD84;

use DB;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Member extends Controller
{

    public function getMember(){
        $data = DB::table('ud84_member')->orderByDesc('ID')->get(['ID','NAMA','LOKASI','ALAMAT','WHATSAPP']);
        return response()->json($data,200);
    }

    public function postMember(Request $request){
        $uniqueID = uniqid();
        DB::table('ud84_member')->insert([
            "UNIQUE"    => $uniqueID,
            "NAMA"      => $request->input('NAMA'),
            "LOKASI"    => $request->input('LOKASI'),
            "ALAMAT"    => $request->input('ALAMAT'),
            "WHATSAPP"  => $request->input('WHATSAPP'),
        ]);

        $keysID     = $request->input('ID');
        $keysPrice  = $request->input('PRICE');

        for($i = 0 ; $i < count($keysID);$i++){
            $itemDetail = DB::table('ud84_master_produk')->where('ID',$keysID[$i])->first();
            DB::table('ud84_member_price')->updateOrInsert([
                "UNIQUE"        => $uniqueID,
                "PRODUCTS_ID"   => $keysID[$i]
            ],[
                "UNIQUE"        => $uniqueID,
                "PRODUCTS_ID"   => $keysID[$i],
                "NAMA"          => $itemDetail->NAMA,
                "HARGA_JUAL"    => empty($keysPrice) ? NULL : $keysPrice[$i],
                "CREATED_AT"    => now()
            ]);
        }

        return response()->json([
            'status'    => 'success',
            'message'   => 'Data tersimpan'
        ],200);
    }

    public function deleteMember(Request $request){
        $ID = $request->input('ID');
        $data = DB::table('ud84_member')->where('ID',$ID)->first();
        DB::table('ud84_member_price')->where('UNIQUE',$data->UNIQUE)->delete();
        DB::table('ud84_member')->where('ID',$ID)->delete();
        return response()->json("OK",200);
    }
}
