<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use DB;

use App\Models\Kosada\AdministratorModel;
use App\Models\Kosada\KreditDetailModel;
use App\Models\Kosada\KreditModel;

class Kredit extends Controller
{
    public function getCustomerData(){
        return response()->json([
            "randomize_ID"  => Str::random(40),
            "memberData"    => AdministratorModel::orderBy('NAMA')->get(['ID','NAMA','ALAMAT','DATA_MARKETING'])
        ],200);
    }

    public function getRealisasiKredit(){
        $data = KreditModel::where('STATUS', 'Yes')->orderByDesc('id')->get(['CREATED_AT','NAMA','STATUS','MARKETING','JUMLAH_PENGAJUAN','KETERANGAN','ID']);
        $currentData = [];
        foreach($data as $data){
            $currentData[] = [
                "ID"                => $data->ID,
                "NAMA"              => $data->NAMA,
                "MARKETING"         => $data->MARKETING,
                "JUMLAH_PENGAJUAN"  => $data->JUMLAH_PENGAJUAN,
                "KETERANGAN"        => $data->KETERANGAN,
                'LUNAS'             => $data->STATUS,
                "CREATED_AT"        => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y'),
            ];
        }
        return response()->json($currentData,200);
    }

    public function postDetailKredit($ID){
        $data           = KreditModel::where('ID',$ID)->first(['ID','NAMA','ALAMAT','NO_KREDIT','KETERANGAN','MARKETING']);
        $detailCicilan  = KreditDetailModel::where('NO_KREDIT', $data->NO_KREDIT)->get(['ID','NOMINAL','KASBON','JATUH_TEMPO','LUNAS','STATUS','UPDATED_AT']);

        $items              = [];
        $totalBelumLunas    = [];
        $kasbonBelumLunas   = [];
        foreach($detailCicilan as $loop){
            $items[] = [
                "ID"            => $loop->ID,
                "NOMINAL"       => $loop->NOMINAL,
                "KASBON"        => $data->KASBON,
                "JATUH_TEMPO"   => Carbon::parse($loop->JATUH_TEMPO)->translatedFormat('d F Y'),
                "LUNAS"         => $loop->LUNAS,
                "STATUS"        => $loop->STATUS,
                "UPDATED_AT"    => Carbon::parse($loop->UPDATED_AT)->translatedFormat('d F Y'),
            ];

            $kasbonBelumLunas[] = $loop->KASBON;

            if($loop->LUNAS == 'Belum'){
                $totalBelumLunas[] = $loop->NOMINAL;
            }
        }

        return response()->json([
            "data"  => [
                "ID"                    => $data->ID,
                "NAMA"                  => $data->NAMA,
                "ALAMAT"                => $data->ALAMAT,
                "MARKETING"             => $data->MARKETING,
                "KETERANGAN"            => $data->KETERANGAN,
                "NO_KREDIT"             => $data->NO_KREDIT,
                "KASBON_BELUM_LUNAS"    => array_sum($kasbonBelumLunas),
                "TOTAL_BELUM_LUNAS"     => array_sum($totalBelumLunas),
                "DETAIL"                => $items
            ]
        ],200);
    }

    public function addKredit(Request $request){
        $DB = DB::table('kosada_member')->where('ID',$request->input('ID'))->first();

        $jangkaWaktu = $request->input('JANGKA_WAKTU');
        $uniqueID = uniqid();

        $fillme = new KreditModel();
        $fillme->NO_KREDIT = $uniqueID;
        $fillme->NAMA = $DB->NAMA;
        $fillme->ALAMAT = $DB->ALAMAT;
        $fillme->MARKETING = $request->input('MARKETING');
        $fillme->JUMLAH_PENGAJUAN = $request->input('JUMLAH_PENGAJUAN');
        $fillme->JANGKA_WAKTU = $jangkaWaktu;
        $fillme->JATUH_TEMPO = $request->input('JATUH_TEMPO');
        $fillme->KETERANGAN = $request->input('KETERANGAN');
        $fillme->STATUS = "Yes";
        $fillme->ADMIN = $request->input('BIAYA_ADMIN');
        $fillme->LUNAS_BRP = 0;
        $fillme->KASBON = NULL;
        $fillme->save();

        for($i = 0 ; $i < $jangkaWaktu; $i++){
            $jatuhTempo = date("Y-m-d",strtotime("+" . $i . " month", strtotime($request->input('JATUH_TEMPO')) ));

            $fill = new KreditDetailModel;
            $fill->NO_KREDIT = $uniqueID;
            $fill->NAMA = $DB->NAMA;
            $fill->NOMINAL = $request->input('ANGSURAN');
            $fill->JATUH_TEMPO = $jatuhTempo;
            $fill->save();
        }
        
        return response()->json([
            "status" => "success",
            "message" => "Data berhasil tersimpan!",
        ],200);
    }

    public function addKasbon(Request $request){
        $ID = $request->input('ID');
        KreditDetailModel::where('ID',$ID )->update([
            "KASBON"   => $request->input('AMOUNT')
        ]);

        $getKey = KreditDetailModel::where('ID',$ID)->first(['NO_KREDIT']);

        KreditModel::where('NO_KREDIT',$getKey->NO_KREDIT)->update([
            "KASBON"    => KreditDetailModel::where('NO_KREDIT',$getKey->NO_KREDIT)->sum('KASBON'),
        ]);

        return response()->json([
            "status" => "success",
            "message" => "Data berhasil tersimpan!",
        ],200);
    }

    public function ubahMarketing(Request $request){
        KreditModel::where('ID',$request->input('ID'))->update([
            "MARKETING" => $request->input('STATUS')
        ]);
        return response()->json([
            "status" => "success",
            "message" => "Data berhasil tersimpan!",
        ],200);
    }

    public function setLunas(Request $request){
        $ID = $request->input('ID');
        
        $getKey = KreditDetailModel::where('ID',$ID)->first(['NO_KREDIT']);
        $countAll = KreditDetailModel::where('NO_KREDIT',$getKey->NO_KREDIT)->count(); // Semua Kasbon
        
        KreditDetailModel::where('ID',$ID)->update([
            "LUNAS" => $request->input('STATUS')
        ]);
        
        $countNotNull = KreditDetailModel::where('NO_KREDIT',$getKey->NO_KREDIT)->where('LUNAS','Sudah')->count();

        KreditModel::where('NO_KREDIT',$getKey->NO_KREDIT)->update([
            "LUNAS_BRP" => $countNotNull
        ]);

        return response()->json([
            "status" => "success",
            "message" => "Data berhasil tersimpan!",
            "lol" => $countAll - $countNotNull
        ],200);
    }

    public function setKreditLunas(Request $request){
        $ID = $request->input('ID');
        $STATUS = $request->input('STATUS');
        KreditModel::where('ID',$ID)->update([
            "STATUS"    => $STATUS,
        ]);
        return response()->json([
            "status" => "success",
            "message" => "Data berhasil diupdate!",
        ],200);
    }

    public function deleteKredit(Request $request){
        $getRows = DB::table('kosada_kredit')->where('ID',$request->input('ID'))->first();
        $uniqueKey = $getRows->NO_KREDIT;

        DB::table('kosada_kredit')->where('NO_KREDIT',$uniqueKey)->delete();
        DB::table('kosada_detail_kredit')->where('NO_KREDIT',$uniqueKey)->delete();
        return response()->json([
            "status" => "success",
            "message" => "Data berhasil terhapus!",
        ],200);
    }
}