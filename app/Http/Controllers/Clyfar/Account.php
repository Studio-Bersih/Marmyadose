<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\DTO\Responses;
use App\DTO\General;
use DB;

class Account extends Controller
{
    public function authorizeAccount(Request $request): JsonResponse {
        $idToken = $request->input('id');

        $userState = new Responses("error","Unauthorized");

        $getUser = DB::table('clyfar_profile')->where('TOKEN', $idToken)->first();

        if (empty($getUser)) {
            return response()->json($userState,200);
        }

        if ($getUser->ENABLE_TEST === 'No') {
            $userState = new Responses("error","Anda tidak dapat mengakses sistem psikotes",null);
        }

        return response()->json(new Responses(
            "success", "Authorized", [
                "statusAccount" => $getUser->LEVEL,
                "userCodes"     => $getUser->TOKEN
            ]
        ),200);
    }

    public function registerAccount(Request $request): JsonResponse {
        $name       = $request->input('name');
        $whatsapp   = $request->input('whatsapp');
        $birthDate  = $request->input('birthDate');
        $gender     = $request->input('gender');
        $pin        = $request->input('localPIN');

        DB::table('clyfar_profile')->updateOrInsert([
            "TOKEN"         => $pin
        ],[
            "TOKEN"         => $pin,
            "WHATSAPP"      => $whatsapp,
            "TTL"           => $birthDate,
            "GENDER"        => $gender,
            "CREATED_AT"    => now(),
        ]);

        $getUser    = DB::table('clyfar_profile')->where('TOKEN', $pin)->first(['LIST']);
        $userTrial  = json_decode($getUser->LIST,true);

        return response()->json(new Responses(
            "success", "Anda dapat memulai test", [
                "setTest"       => $userTrial,
                "currentTest"   => $userTrial[0]
            ]
        ));
    }

    public function logOut(Request $request): JsonResponse {
        $whatsapp = $request->input('whatsapp');
        return response()->json(new General("success","Anda telah menyelesaikan psikotes dengan sukses!"));
    }
}