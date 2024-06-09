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
        } else if ($testType === 'PAPI') {
            if ($userToken === 'Lumos') {
                return response()->json(new General("success","Selamat mengerjakan"),200);
            }
        }  else if ($testType === 'Kraepelin') {
            if ($userToken === 'Reparo') {
                return response()->json(new General("success","Selamat mengerjakan"),200);
            }
        }  else if ($testType === 'BAUM') {
            if ($userToken === 'Finite') {
                return response()->json(new General("success","Selamat mengerjakan"),200);
            }
        }  else if ($testType === 'MBTI') {
            if ($userToken === 'Sonorus') {
                return response()->json(new General("success","Selamat mengerjakan"),200);
            }
        }

        return response()->json(new General("error","Token anda tidak sesuai!"),200);
    }

    public function postTest(Request $request): JsonResponse {
        $testType = $request->input('TIPE');

        $data = $request->all();
        Log::info($data);
        
        if($testType === 'DISC') {
            $DISC = $request->input('DISC');
        } else if ($testType === 'PAPI') {
            $PAPI = $request->input('PAPI');
        } else if ($testType === 'Kraepelin') {
            $Kraepelin = $request->input('Kraepelin');
        }
        
        return response()->json(new Test("success","Anda akan diarahkan ke subtes berikutnya","MSDT"));
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
