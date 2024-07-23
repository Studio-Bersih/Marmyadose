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
            "success", "Berhasil dimuat", $data
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

    public function viewTestee(Request $request): JsonResponse {
        $token = $request->input('token');
        $DB = DB::table('clyfar_test')->where('KODE',$token)->first();
        
        if (empty($DB)) {
            return response()->json(new Responses(
                "error","Kandidat belum melakukan tes."
            ));
        }

        return response()->json(new Responses(
            "success","Data berhasil dimuat", $DB->ID
        ));
    }

    public function viewResult(Request $request): JsonResponse {
        $id = $request->input('id');

        $DB = DB::table('clyfar_test')->where('ID',$id)->first();
        $data = [
            "DISC"      => empty($DB->DISC) ? [] : json_decode($DB->DISC, true),
            "PAPI"      => empty($DB->PAPI) ? [] : $this->interpretPapikostick(json_decode($DB->PAPI, true)),
            "KRAEPLIN"  => empty($DB->KRAEPLIN) ? [] : json_decode($DB->KRAEPLIN),
            "BAUM"      => empty($DB->BAUM) ? null : $DB->BAUM,
            "MBTI"      => empty($DB->MBTI) ? null : json_decode($DB->MBTI),
            "MSDT"      => empty($DB->MSDT) ? [] : json_decode($DB->MSDT),
            "CFIT"      => empty($DB->CFIT) ? [] : json_decode($DB->CFIT),
        ];

        return response()->json(new Responses(
            "success", "Data berhasil dimuat", $data
        ));
    }

    private function interpretPapikostick($rawPapi) {
        $groups = [
            'G' => [0, 10, 20, 30, 40, 50, 60, 70, 80],
            'A' => ['A' => [1], 'B' => [2, 13, 24, 35, 46, 57, 68, 79]],
            'L' => ['A' => [11, 21, 31, 41, 51, 61, 71, 81], 'B' => [80]],
            'P' => ['A' => [12, 2], 'B' => [3, 14, 25, 36, 47, 58, 69]],
            'I' => ['A' => [22, 32, 42, 52, 62, 72, 82], 'B' => [70, 81]],
            'T' => ['A' => [33, 43, 53, 63, 73, 83], 'B' => [60, 71, 82]],
            'V' => ['A' => [44, 54, 64, 74, 84], 'B' => [50, 61, 72, 83]],
            'X' => ['A' => [3, 13, 23], 'B' => [4, 15, 26, 37, 48, 59]],
            'S' => ['A' => [55, 65, 75, 85], 'B' => [40, 51, 62, 73, 84]],
            'B' => ['A' => [4, 14, 24, 34], 'B' => [5, 16, 27, 38, 49]],
            'O' => ['A' => [5, 15, 25, 35, 45], 'B' => [6, 17, 28, 39]],
            'R' => ['A' => [66, 76, 86], 'B' => [30, 41, 52, 63, 74, 85]],
            'D' => ['A' => [77, 87], 'B' => [20, 31, 42, 53, 64, 75, 86]],
            'C' => ['A' => [88], 'B' => [10, 21, 32, 43, 54, 65, 76, 87]],
            'Z' => ['A' => [6, 16, 26, 36, 46, 56], 'B' => [7, 18, 29]],
            'E' => ['B' => [0, 11, 22, 33, 44, 55, 66, 77, 88]],
            'K' => ['A' => [7, 17, 27, 37, 47, 57, 67], 'B' => [8, 19]],
            'F' => ['A' => [8, 18, 28, 38, 48, 58, 68, 78], 'B' => [9]],
            'W' => ['A' => [9, 19, 29, 39, 49, 59, 69, 79, 89]],
            'N' => ['B' => [1, 12, 23, 34, 45, 56, 67, 78, 89]],
        ];
    
        $result = [];
    
        foreach ($groups as $groupKey => $subGroups) {
            foreach ($subGroups as $subGroupKey => $indexes) {
                foreach ($indexes as $index) {
                    if (!isset($result[$groupKey])) {
                        $result[$groupKey] = [];
                    }
                    if (!isset($result[$groupKey][$subGroupKey])) {
                        $result[$groupKey][$subGroupKey] = 0;
                    }
                    if ($rawPapi[$index] === $subGroupKey) {
                        $result[$groupKey][$subGroupKey]++;
                    }
                }
            }
        }
    
        $descriptions = [
            'A' => "Work Direction",
            'N' => "Work Direction",
            'G' => "Work Direction",
            'C' => "Work Style",
            'D' => "Work Style",
            'R' => "Work Style",
            'T' => "Activity",
            'V' => "Activity",
            'W' => "Followership",
            'F' => "Followership",
            'L' => "Leadership",
            'P' => "Leadership",
            'I' => "Leadership",
            'S' => "Social Nature",
            'B' => "Social Nature",
            'O' => "Social Nature",
            'X' => "Social Nature",
            'E' => "Temperament",
            'K' => "Temperament",
            'Z' => "Temperament",
        ];
    
        $finalPapi = [];
    
        foreach ($descriptions as $key => $description) {
            $nilai = isset($result[$key]['A']) ? $result[$key]['A'] : 0;
            if (isset($result[$key]['B'])) {
                $nilai += $result[$key]['B'];
            }
            $finalPapi[$key] = [
                "NILAI" => $nilai,
                "Deskripsi" => $description,
            ];
        }
    
        return $finalPapi;
    }
}
