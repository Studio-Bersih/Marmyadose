<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\DTO\General;
use App\DTO\Test;
use Log;

class Psychological extends Controller
{

    public function verifyToken(Request $request): JsonResponse {
        $validTokens = [
            'DISC' => 'Veritas',
            'PAPI' => 'Lumos',
            'Kraepelin' => 'Reparo',
            'BAUM' => 'Finite',
            'MBTI' => 'Sonorus',
            'MSDT' => 'Stupefy',
        ];
    
        $userToken = $request->input('token');
        $testType = $request->input('type');
    
        if (isset($validTokens[$testType]) && $userToken === $validTokens[$testType]) {
            return response()->json(new General("success", "Selamat mengerjakan"), 200);
        }
    
        return response()->json(new General("error", "Token anda tidak sesuai!"), 200);
    }
    

    public function postTest(Request $request): JsonResponse {
        $testType = $request->input('TIPE');
        
        if($testType === 'DISC') {
            $DISC = $request->input('DISC');
            // Input $DISC to database!
        } else if ($testType === 'PAPI') {
            $PAPI = $request->input('PAPI');
            // Input $PAPI to database!
        } else if ($testType === 'Kraepelin') {
            $Kraepelin = $request->input('Kraepelin');
            // Input $Kraepelin to database!
        } else if ($testType === 'MBTI') {
            $MBTI = $request->input('MBTI');
            // Input $MBTI to database!
        } else if ($testType === 'MSDT') {
            $MSDT = $request->input('MSDT');
            // Input $MSDT to database!
        }
        
        return response()->json(new Test("success","Anda akan diarahkan ke subtes berikutnya","CFIT"));
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
