<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;

class Report extends Controller
{
    public function getReport(Request $request){
        $startDate      = $request->input('TANGGAL_AWAL');
        $endDate        = $request->input('TANGGAL_AKHIR');
        $dataMarketing  = $request->input('MARKETING');

        $DB = DB::table('kosada_kredit')->where('MARKETING',$dataMarketing)->whereBetween('CREATED_AT',[
            $startDate,$endDate
        ])->orderByDesc('ID')->get(['NAMA','NO_KREDIT','LUNAS_BRP','JANGKA_WAKTU','JUMLAH_PENGAJUAN','KASBON','CREATED_AT']);

        $data = [];
        foreach($DB as $DB){
            $nominalCicilan = DB::table('kosada_detail_kredit')->where('NO_KREDIT',$DB->NO_KREDIT)->first(['NOMINAL']);
            $data[] = [
                "NAMA"          => $DB->NAMA,
                "CICILAN_TOTAL" => $nominalCicilan->NOMINAL,
                "KASBON"        => $DB->KASBON,
                "PROGRESS"      => $DB->LUNAS_BRP . " / " . $DB->JANGKA_WAKTU,
                "CREATED_AT"    => Carbon::parse($DB->CREATED_AT)->translatedFormat('d F Y'),
            ];
        }

        return response()->json($data,200);
    }
}
