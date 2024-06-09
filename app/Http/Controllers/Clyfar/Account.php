<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\DTO\Responses;

class Account extends Controller
{
    public function authorizeAccount(Request $request): JsonResponse {
        $idToken = $request->input('id');

        $userState = new Responses("error","Unauthorized");

        if ($idToken === 'URXVT') {
            return response()->json(new Responses(
                "success", "Authorized","Administrator"
            ),200);
        }

        return response()->json($userState,200);
    }
}