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
        $userToken  = $request->input('token');
        $testType   = $request->input('type');

        if ($testType === 'DISC') {
            if ($userToken === 'Veritas') {
                return response()->json(new General("success","Selamat mengerjakan"),200);
            }
        }

        return response()->json(new General("error","Token anda tidak sesuai!"),200);
    }

    public function postTest(Request $request): JsonResponse {
        $testType = $request->input('TIPE');
        
        if($testType === 'DISC') {
            $DISC = $request->input('DISC');
            // Check DISC.txt di folder test di dalam Clyfar!
        }
        
        return response()->json(new Test("success","Anda akan diarahkan ke subtes berikutnya","PAPI"));
    }
}
