<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\DTO\Responses;
use Log;

class Users extends Controller
{
    public function checkToken(Request $request){
        $token = $request->input('token');

        $DB = DB::table('pos_users')->where('TOKEN', $token)->first();

        if(empty($DB)) {
            return response()->json(new Responses(
                "error", "Token tidak ditemukan!"
            ));
        }

        return response()->json(new Responses(
            "success","Authorized",[
                "token" => $token,
                "roles" => $DB->ROLE,
                "usaha" => $DB->USAHA
            ]
        ));
    }

    public function getUsers(Request $request): JsonResponse {
        $DB = DB::table('pos_users')->where('USAHA', $request->input('usaha'))->get();
        return response()->json(new Responses(
            "success","Data berhasil dimuat", $DB
        ));
    }

    public function updateUsers(Request $request): JsonResponse {
        $id = $request->input('id');
        $token = $request->input('token');
        $cabang = $request->input('cabang');
        $usaha = $request->input('usaha');

        DB::table('pos_users')->where('ID', $id)->update([
            "TOKEN" => $token,
            "CABANG" => $cabang,
            "USAHA" => $usaha
        ]);

        return response()->json(new Responses(
            "success","Data berhasil diupdate!"
        ));
    }
}
