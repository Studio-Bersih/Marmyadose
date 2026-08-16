<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use DB;

class Report extends Controller
{
    public function getReport(Request $request){
        $startDate      = $request->input('TANGGAL_AWAL');
        $endDate        = $request->input('TANGGAL_AKHIR');
        $dataMarketing  = $request->input('MARKETING');
        $nama           = $request->input('NAMA');

        $query = DB::table('kosada_kredit')->whereBetween('CREATED_AT',[
            $startDate,$endDate
        ])->where('STATUS','Yes');

        // 'SEMUA' means every marketing — the page had no such option before, so
        // producing an all-marketing monthly report was impossible.
        if($dataMarketing && $dataMarketing != 'SEMUA'){
            $query = $query->where('MARKETING',$dataMarketing);
        }

        if(!empty($nama)){
            $query = $query->where('NAMA','LIKE','%' . $nama . '%');
        }

        // Hidden rows are excluded here. See Report@toggleHidden: staff can drop a
        // settled loan off the report without deleting anything.
        $query = $query->where(function($q){
            $q->where('HIDDEN_FROM_REPORT',0)->orWhereNull('HIDDEN_FROM_REPORT');
        });

        $DB = $query->orderByDesc('ID')->get(['ID','NAMA','NO_KREDIT','LUNAS_BRP','JANGKA_WAKTU','JUMLAH_PENGAJUAN','KASBON','CREATED_AT']);

        /*
        | Installment amounts for every loan in the result, in ONE query.
        |
        | This used to run a separate SELECT per loan inside the loop below. With a
        | single marketing that was ~161 queries and merely slow; once the page
        | gained its "SEMUA" option it became ~5,249 queries against an unindexed
        | TEXT column and the request died on PHP's 30-second limit.
        |
        | MIN(NOMINAL) reproduces the old ->first(['NOMINAL']) result: addKredit
        | writes the same ANGSURAN to every installment of a loan, so all rows share
        | one NOMINAL. This is the MONTHLY figure, not the whole loan — the column is
        | named CICILAN_TOTAL for backward compatibility only. Do not rename it
        | without checking the frontend.
        */
        $nominalPerKredit = DB::table('kosada_detail_kredit')
            ->whereIn('NO_KREDIT', $DB->pluck('NO_KREDIT')->all())
            ->groupBy('NO_KREDIT')
            ->selectRaw('NO_KREDIT, MIN(NOMINAL) as NOMINAL')
            ->pluck('NOMINAL','NO_KREDIT');

        $data = [];
        foreach($DB as $row){
            $cicilan = (int) ($nominalPerKredit[$row->NO_KREDIT] ?? 0);
            $kasbon  = (int) $row->KASBON;

            $data[] = [
                "ID"            => $row->ID,
                "NAMA"          => $row->NAMA,
                "CICILAN_TOTAL" => $cicilan,
                "KASBON"        => $kasbon,
                // Rightmost TOTAL column: what this member owes this month.
                "TOTAL"         => $kasbon + $cicilan,
                "PROGRESS"      => $row->LUNAS_BRP . " / " . $row->JANGKA_WAKTU,
                "CREATED_AT"    => Carbon::parse($row->CREATED_AT)->translatedFormat('d F Y'),
            ];
        }

        return response()->json($data,200);
    }

    /*
    | Hide a loan from the Laporan, or put it back.
    |
    | This touches nothing but the flag. The loan and all its installments stay in
    | the database and stay visible on the Dashboard — the client wanted settled
    | rows out of the monthly report, not erased.
    */
    public function toggleHidden(Request $request){
        /*
        | Validator::make rather than $request->validate(): the latter throws a
        | ValidationException, which Laravel renders as a 302 redirect to HTML
        | unless the caller sent `Accept: application/json`. The frontend sends
        | only Content-Type, so it would receive HTML and fail on .json().
        */
        $validator = Validator::make($request->all(),[
            'ID'     => ['required','integer'],
            'HIDDEN' => ['required','boolean'],
        ],[
            'ID.required'     => 'Data kredit wajib dipilih',
            'HIDDEN.required' => 'Status sembunyikan wajib diisi',
        ]);

        if($validator->fails()){
            return response()->json([
                "status"  => "error",
                "message" => $validator->errors()->first(),
            ],422);
        }

        $affected = DB::table('kosada_kredit')
            ->where('ID',$request->input('ID'))
            ->update(['HIDDEN_FROM_REPORT' => $request->boolean('HIDDEN') ? 1 : 0]);

        if($affected === 0){
            return response()->json([
                "status"  => "error",
                "message" => "Data kredit tidak ditemukan!",
            ],404);
        }

        return response()->json([
            "status"  => "success",
            "message" => $request->boolean('HIDDEN')
                ? "Data disembunyikan dari laporan!"
                : "Data ditampilkan kembali di laporan!",
        ],200);
    }

    /*
    | The rows currently hidden, so staff can review and restore them.
    */
    public function getHidden(Request $request){
        $data = DB::table('kosada_kredit')
            ->where('HIDDEN_FROM_REPORT',1)
            ->orderByDesc('ID')
            ->get(['ID','NAMA','MARKETING','JUMLAH_PENGAJUAN','KASBON','LUNAS_BRP','JANGKA_WAKTU','CREATED_AT'])
            ->map(function($row){
                return [
                    "ID"          => $row->ID,
                    "NAMA"        => $row->NAMA,
                    "MARKETING"   => $row->MARKETING,
                    "KASBON"      => (int) $row->KASBON,
                    "PROGRESS"    => $row->LUNAS_BRP . " / " . $row->JANGKA_WAKTU,
                    "CREATED_AT"  => Carbon::parse($row->CREATED_AT)->translatedFormat('d F Y'),
                ];
            });

        return response()->json($data,200);
    }
}
