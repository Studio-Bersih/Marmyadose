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

        $getUser = DB::table('users')->where('token', $idToken)->first();

        if (empty($getUser)) {
            return response()->json($userState,200);
        }

        if ($getUser->enable_test === 'No') {
            $userState = new Responses("error","Anda tidak dapat mengakses sistem psikotes",null);
        }

        return response()->json(new Responses(
            "success", "Authorized", $getUser->level
        ),200);
    }

    public function registerAccount(Request $request): JsonResponse {
        $name = $request->input('name');
        $whatsapp = $request->input('whatsapp');
        $birthDate = $request->input('birthDate');
        $gender = $request->input('gender');

        // Update users information!

        // DB::table('???')->where('token','???')->update([
        //     "nama"          => $name,
        //     "whatsapp"      => $whatsapp,
        //     "tanggal"       => $birthDate,
        //     "gender"        => $gender,
        //     "updated_at"    => now()
        // ]);

        // Get users test information
        // $getUser = DB::table('users')->where('token', $idToken)->first(['test']);
        
        return response()->json(new Responses(
            "success", "Anda dapat memulai test", [
                "setTest"       => ['DISC','PAPI','KRAEPLIN','BAUM','MBTI','MSDT','CFIT'],
                "currentTest"   => 'DISC'
            ]
        ));
    }

    public function logOut(Request $request): JsonResponse {
        $whatsapp = $request->input('whatsapp');
        return response()->json(new General("success","Anda telah menyelesaikan psikotes dengan sukses!"));
    }
}