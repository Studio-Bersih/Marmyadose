<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\DTO\General;
use App\DTO\Test;
use Log;

class Psychological extends Controller
{

    public function verifyToken(Request $request): JsonResponse {
        $validTokens = [
            'DISC'      => 'Veritas',
            'PAPI'      => 'Lumos',
            'Kraepelin' => 'Reparo',
            'BAUM'      => 'Finite',
            'MBTI'      => 'Sonorus',
            'MSDT'      => 'Stupefy',
        ];
    
        $userToken  = $request->input('token');
        $testType   = $request->input('type');
    
        if (isset($validTokens[$testType]) && $userToken === $validTokens[$testType]) {
            return response()->json(new General("success", "Selamat mengerjakan"), 200);
        }
    
        return response()->json(new General("error", "Token anda tidak sesuai!"), 200);
    }
    

    public function postTest(Request $request): JsonResponse {
        $testType = $request->input('TIPE');
        $pin = $request->input('localPIN');
    
        if (empty($pin)) {
            return response()->json(new General("error", "Sesi anda telah selesai"), 200);
        }
    
        $testFields = [
            'DISC'      => 'DISC',
            'PAPI'      => 'PAPI',
            'Kraepelin' => 'KRAEPLIN',
            'MBTI'      => 'MBTI',
            'MSDT'      => 'MSDT'
        ];
    
        if (!isset($testFields[$testType])) {
            return response()->json(new General("error", "Invalid test type"), 400);
        }
    
        $field = $testFields[$testType];

        DB::beginTransaction();
        try {
            DB::table('clyfar_test')->updateOrInsert([
                "KODE" => $pin
            ],[
                "KODE" => $pin,
                $field  => json_encode($request->input('data'))
            ]);
    
            $testLeft = DB::table('clyfar_profile')->where('TOKEN', $pin)->first(['LIST']);
            $listOfTest = json_decode($testLeft->LIST, true); // ["DISC","PAPI","KRAEPLIN","BAUM","MBTI","MSDT","CFIT"]
    
            // Remove the completed test from the list
            $listOfNewTest = array_diff($listOfTest, [$testType]);
            $newTest       = array_values($listOfNewTest);
    
            // Update the database with the new list
            DB::table('clyfar_profile')->where('TOKEN', $pin)->update([
                "LIST" => json_encode($newTest)
            ]);
    
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(new General("error", "Database error"), 500);
        }
    
        return response()->json(new Test("success", "Anda akan diarahkan ke subtes berikutnya", $newTest[0]));
    }

    public function postBaum(Request $request): JsonResponse {
        if (!$request->hasFile('baum')) {
            return response()->json(new General("error","Tidak ada file yang dilampirkan"));
        }

        $image          = $request->file('baum');
        $imageName      = $image->hashName(); // Ntar ini diinput di database
        $image->move(public_path('BAUM/'), $imageName);

        return response()->json(new Test("success","Anda akan diarahkan ke subtes berikutnya","MBTI"));
    }

}
