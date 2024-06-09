<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Account extends Controller
{
    public function authorizeAccount(Request $request) {
        return response()->json([
            "message" => "Hello!"
        ],200);
    }
}
