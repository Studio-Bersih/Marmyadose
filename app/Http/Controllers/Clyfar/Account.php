<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\DTO\Responses;
use App\DTO\General;
use Log;

class Account extends Controller
{
    public function authorizeAccount(Request $request): JsonResponse {
        $idToken = $request->input('id');
        Log::info($idToken);

        $userState = new Responses("error","Token tidak terdaftar!");

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

        Log::info($request->all());

        DB::table('clyfar_profile')->where('TOKEN',$pin)->update([
            "NAMA"          => $name,
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

    public function createAccount(Request $request): JsonResponse {
        $amount = $request->input('amount');
        $gender = $request->input('gender');

        $MSDT       = $request->input('MSDT');
        $CFIT       = $request->input('CFIT');
        $MBTI       = $request->input('MBTI');
        $KRAEPLIN   = $request->input('KRAEPLIN');
        $BAUM       = $request->input('BAUM');
        $DISC       = $request->input('DISC');
        $PAPI       = $request->input('PAPI');

        $testType = [];

        $testTypes = ['MSDT', 'CFIT', 'MBTI', 'KRAEPLIN', 'BAUM', 'DISC', 'PAPI'];
        // Filter and reindex to remove gaps
        $testType = array_values(array_filter($testTypes, fn($type) => $request->input($type) !== null));

        try {
            $generateUser = [];
            DB::beginTransaction();
                for($i = 0; $i < $amount; $i++) {
                    $generateUser[] = [
                        "TOKEN"     => strtoupper(substr(bin2hex(random_bytes(4)), 0, 7)),
                        "GENDER"    => $gender,
                        "LIST"      => json_encode($testType)
                    ];
                }
                DB::table('clyfar_profile')->insert($generateUser);
            DB::commit();

            return response()->json(new Responses(
                "success", "User berhasil dibuat"
            ));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(new Responses(
                "error", "Ada kesalahan pada server!"
            ));
        }
    }

    public function logOut(Request $request): JsonResponse {
        $whatsapp = $request->input('whatsapp');
        return response()->json(new General("success","Anda telah menyelesaikan psikotes dengan sukses!"));
    }
}