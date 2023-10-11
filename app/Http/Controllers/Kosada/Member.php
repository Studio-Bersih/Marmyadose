<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\AdministratorModel;

class Member extends Controller
{
    public function getMember(){
        $data = AdministratorModel::orderByDesc('CREATED_AT')->get([
            'ID','NAMA','ALAMAT','KOTA',
            'TELEPON','CREATED_AT','KETERANGAN',
            'DATA_MARKETING','KTP','PIN_ATM',
            'GENDER','REKOMENDASI_DARI','PEKERJAAN'
        ]);
        $currentData = [];
        foreach($data as $data){
            $currentData[] = [
                "ID"            => $data->ID,
                "NAMA"          => ucwords(strtolower($data->NAMA)),
                "ALAMAT"        => strtoupper($data->ALAMAT),
                "KOTA"          => $data->KOTA,
                "HP"            => $data->TELEPON,
                "GENDER"        => $data->GENDER,
                "REKOMENDASI"   => $data->REKOMENDASI_DARI,
                "KTP"           => $data->KTP,
                "PIN_ATM"       => $data->PIN_ATM,
                "PEKERJAAN"     => $data->PEKERJAAN,
                "KETERANGAN"    => $data->KETERANGAN,
                "MARKETING"     => $data->DATA_MARKETING,
                "CREATED_AT"    => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y'),
            ];
        }
        return response()->json($currentData,200);
    }

    public function addMember(Request $request){
        $ID                 = $request->input('ID');
        $NAMA               = ucwords(strtolower($request->input('NAMA')));
        $ALAMAT             = $request->input('ALAMAT');
        $KOTA               = $request->input('KOTA');
        $PROVINSI           = $request->input('PROVINSI');
        $TELEPON            = $request->input('WHATSAPP');
        $NO_KTP             = $request->input('KTP');
        $PIN_ATM            = $request->input('PIN');
        $GENDER             = $request->input('GENDER');
        $DATA_MARKETING     = $request->input('MARKETING');
        $PEKERJAAN          = $request->input('PEKERJAAN');
        $REKOMENDASI_DARI   = $request->input('REKOMENDASI');
        $KETERANGAN         = $request->input('KETERANGAN');

        empty($ID) ? $fillme = new AdministratorModel : $fillme = AdministratorModel::find($ID);

        $fillme->NAMA               = $NAMA;
        $fillme->ALAMAT             = $ALAMAT;
        $fillme->KOTA               = $KOTA;
        $fillme->PROVINSI           = $PROVINSI;
        $fillme->TELEPON            = $TELEPON;
        $fillme->KTP                = $NO_KTP;
        $fillme->PIN_ATM            = $PIN_ATM;
        $fillme->GENDER             = $GENDER;
        $fillme->DATA_MARKETING     = $DATA_MARKETING;
        $fillme->PEKERJAAN          = $PEKERJAAN;
        $fillme->REKOMENDASI_DARI   = $REKOMENDASI_DARI;
        $fillme->KETERANGAN         = $KETERANGAN;
        $fillme->save();

        return response()->json([
            'status'    => 'success',
            'message'   => 'Data berhasil tersimpan!'
        ],200);
    }

    public function deleteMember(Request $request){
        DB::table('kosada_member')->where('ID',$request->input('ID'))->delete();
        return response()->json([
            'status' => 'success',
            'message'=> 'Member berhasil dihapus!',
        ],200);
    }
}
