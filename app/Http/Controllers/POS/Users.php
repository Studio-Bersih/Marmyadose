<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\DTO\Responses;

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
                "roles" => $DB->ROLE
            ]
        ));
    }

    public function getUsers(): JsonResponse {
        $DB = DB::table('pos_users')->get(['ID','TOKEN','ROLE']);
        return response()->json(new Responses(
            "success","Data berhasil dimuat", $DB
        ));
    }

    public function updateUsers(Request $request): JsonResponse {
        $id = $request->input('id');
        $token = $request->input('token');

        DB::table('pos_users')->where('ID', $id)->update([
            "TOKEN" => $token
        ]);
        return response()->json(new Responses(
            "success","Data berhasil diupdate!"
        ));
    }
}
