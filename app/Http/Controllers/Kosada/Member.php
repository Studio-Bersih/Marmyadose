<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;

use App\Models\Kosada\AdministratorModel;

class Member extends Controller
{
    /*
    | The Anggota Koperasi list.
    |
    | Paginated since 2026-08-16. It previously returned the entire member table on
    | every request -- 2,736 rows and 901 KB -- which the browser then filtered in
    | an array. Filtering moved to the server at the same time.
    |
    | Response shape changed from a bare array to { data, meta }. Both callers
    | (member/+page.server.ts and member/+page.svelte) were updated with it.
    */
    public function getMember(Request $request){
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 200));
        $page    = max(1, (int) $request->input('page', 1));

        $query = AdministratorModel::query();

        if($request->filled('nama')){
            $query = $query->where('NAMA','LIKE','%' . $request->input('nama') . '%');
        }

        $marketing = $request->input('marketing');
        if(!empty($marketing) && $marketing != 'SEMUA'){
            $query = $query->where('DATA_MARKETING',$marketing);
        }

        $total = (clone $query)->count();

        $data = $query->orderByDesc('CREATED_AT')
            ->forPage($page, $perPage)
            ->get([
                'ID','NAMA','ALAMAT','KOTA',
                'TELEPON','CREATED_AT','KETERANGAN',
                'DATA_MARKETING','KTP','PIN_ATM',
                'GENDER','REKOMENDASI_DARI','PEKERJAAN','PROVINSI'
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
                "PROVINSI"      => $data->PROVINSI,
                "CREATED_AT"    => Carbon::parse($data->CREATED_AT)->translatedFormat('d F Y'),
            ];
        }

        return response()->json([
            "data" => $currentData,
            "meta" => [
                "page"      => $page,
                "per_page"  => $perPage,
                "total"     => $total,
                "last_page" => (int) ceil(max(1,$total) / $perPage),
            ],
        ],200);
    }

    public function addMember(Request $request){

        $ID                 = $request->input('ID');
        $NAMA               = ucwords(strtolower($request->input('NAMA')));
        $ALAMAT             = $request->input('ALAMAT');
        $KOTA               = $request->input('KOTA');
        $PROVINSI           = $request->input('PROVINSI');
        $TELEPON            = strval($request->input('WHATSAPP'));
        $NO_KTP             = strval($request->input('KTP'));
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
            'message'   => 'Data berhasil tersimpan!',
        ],200);
    }

    public function updateMember(Request $request){
        $ID                 = $request->input('ID');
        $NAMA               = ucwords(strtolower($request->input('NAMA')));
        $ALAMAT             = $request->input('ALAMAT');
        $KOTA               = $request->input('KOTA');
        $PROVINSI           = $request->input('PROVINSI');
        $TELEPON            = strval($request->input('WHATSAPP'));
        $NO_KTP             = strval($request->input('KTP'));
        $PIN_ATM            = $request->input('PIN');
        $GENDER             = $request->input('GENDER');
        $DATA_MARKETING     = $request->input('MARKETING');
        $PEKERJAAN          = $request->input('PEKERJAAN');
        $REKOMENDASI_DARI   = $request->input('REKOMENDASI');
        $KETERANGAN         = $request->input('KETERANGAN');

        $fillme = AdministratorModel::find($ID);
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
            'message'   => 'Data berhasil diupdate!',
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
