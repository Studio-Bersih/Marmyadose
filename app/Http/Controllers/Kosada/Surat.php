<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\Kosada\Surats;

class Surat extends Controller
{
    public function getSurat(){
        $DB = Surats::orderByDesc('ID')->get();
        $data = [];
    
        foreach($DB as $db){
            $data[] = [
                "ID"            => $db->ID,
                "NO_SURAT"      => $db->NO,
                "TANGGAL_SURAT" => Carbon::parse($db->CREATED_AT)->translatedFormat('d F Y'),
                "LAMPIRAN"      => $db->LAMPIRAN,
                "PERIHAL"       => $db->HAL,
            ];
        }
        return response()->json([
            "data"  => $data
        ],200);
    }

    public function postSurat(Request $request){
        Surats::UpdateOrCreate([
            "NO"        => $request->input('NOMOR_SURAT')
        ],[
            "NO"        => $request->input('NOMOR_SURAT'),
            "LAMPIRAN"  => $request->input('LAMP'),
            "HAL"       => $request->input('HAL'),
            "TEKS"      => $request->input('KONTEN'),
        ]);
        return response()->json([
            'status'    => 'success',
            'message'   => 'Data berhasil disimpan!'
        ],200);
    }

    public function lihatSurat($ID){
        return response()->json([
            "data"  => Surats::where('ID',$ID)->first()
        ],200);
    }
}
