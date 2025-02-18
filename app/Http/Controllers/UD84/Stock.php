<?php

namespace App\Http\Controllers\UD84;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Stock extends Controller
{
    public function getUser(){
        return response()->json([
            "status"    => "success",
            "message"   => "OK",
            "data"      => [
                [
                    "ID" => 1,
                    "NAMA" => "Agus"
                ],
                [
                    "ID" => 2,
                    "NAMA" => "Budi"
                ],
                [
                    "ID" => 3,
                    "NAMA" => "Caca"
                ]
            ]
        ],200);
    }
}
