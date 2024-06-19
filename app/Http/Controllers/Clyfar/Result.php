<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\DTO\Responses;
use App\DTO\General;

class Result extends Controller
{
    public function getTestee(): JsonResponse {
        $data = DB::table('clyfar_profile')->skip(0)->take(30)->orderByDesc('ID')->get();
        return response()->json(new Responses(
            "success","Berhasil dimuat",$data
        ));
    }

    public function createTestee(Request $request): JsonResponse {
        $amount = $request->input('amount');
        $test = $request->input('tests');

        $data = [];
        for($i = 0; $i < $amount; $i++) {
            $data[] = [
                "TOKEN"         => strtoupper(substr(Str::random(6), 0, 6)),
                "LIST"          => json_encode($test),
                "ENABLE_TEST"   => "Yes",
                "LEVEL"         => "Normal",
                "CREATED_AT"    => now()
            ];
        }

        DB::table('clyfar_profile')->insert($data);

        return response()->json(new Responses(
            "success", "Akun berhasil dibuat"
        ));
    }
}
