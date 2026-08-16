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
    /*
    | Setup data for the Tambah Kredit form.
    |
    | This used to return every member (2,736 rows, 311 KB) to populate a <select>,
    | which the browser then rendered as 2,736 options. The page now uses the
    | typeahead at /Kosada/Cari-Member instead, so only the generated credit number
    | is needed here.
    */
    public function getCustomerData(){
        return response()->json([
            "randomize_ID"  => Str::random(40),
        ],200);
    }

    /*
    | The Dashboard's loan list.
    |
    | Paginated since 2026-08-16. It previously returned every matching loan --
    | ~5,249 rows and 789 KB of JSON for a wide date range. The query itself was
    | never the problem (24 ms); the cost was building, transferring and rendering
    | the payload. Returning a page cuts the query to 0.2 ms and the response to a
    | few KB.
    |
    | Response shape changed from a bare array to { data, meta }. The only caller
    | is Kosada's dashboard page, updated in the same change.
    */
    public function getRealisasiKreditRange(Request $request) {
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 200));
        $page    = max(1, (int) $request->input('page', 1));

        $data = KreditModel::where('CREATED_AT', '>=', $request->input('start'))->where('CREATED_AT', '<=', $request->input('end'))
        ->where('STATUS', 'Yes');

        if ($request->input('kategori') != "SEMUA") {
            $data = $data->where('MARKETING', $request->input('kategori'));
        }

        if ($request->filled('nama')) {
            $data = $data->where('NAMA', 'LIKE', '%' . $request->input('nama') . '%');
        }

        $total = (clone $data)->count();

        $rows = $data->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get(['CREATED_AT','NAMA','STATUS','MARKETING','JUMLAH_PENGAJUAN','KETERANGAN','ID'])
            ->map(function($item) {
                return [
                    "ID"                => $item->ID,
                    "NAMA"              => $item->NAMA,
                    "MARKETING"         => $item->MARKETING,
                    "JUMLAH_PENGAJUAN"  => $item->JUMLAH_PENGAJUAN,
                    "KETERANGAN"        => $item->KETERANGAN,
                    'LUNAS'             => $item->STATUS,
                    "CREATED_AT"        => Carbon::parse($item->CREATED_AT)->translatedFormat('d F Y'),
                ];
            })->toArray();

        return response()->json([
            "data" => $rows,
            "meta" => [
                "page"      => $page,
                "per_page"  => $perPage,
                "total"     => $total,
                "last_page" => (int) ceil(max(1,$total) / $perPage),
            ],
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
                // Must read $loop, not $data. $data is the loan header, whose KASBON is
                // the denormalised SUM of every installment's kasbon — using it here gave
                // every row the same figure and made the per-row TOTAL wrong.
                "KASBON"        => $loop->KASBON,
                "JATUH_TEMPO"   => Carbon::parse($loop->JATUH_TEMPO)->translatedFormat('d F Y'),
                "LUNAS"         => $loop->LUNAS,
                "STATUS"        => $loop->STATUS,
                "UPDATED_AT"    => Carbon::parse($loop->UPDATED_AT)->translatedFormat('d F Y'),
            ];

            // Only unpaid installments count toward either figure. This used to sum
            // kasbon across every row regardless of LUNAS, so a loan whose kasbon sat
            // on an already-settled installment still reported it as outstanding.
            if($loop->LUNAS == 'Belum'){
                $kasbonBelumLunas[] = $loop->KASBON;
                $totalBelumLunas[]  = $loop->NOMINAL;
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
        $fillme->MEMBER_ID = $DB->ID;
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