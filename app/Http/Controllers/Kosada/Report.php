<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use DB;

class Report extends Controller
{
    /*
    | The Laporan screen — paginated.
    |
    | It previously returned every matching loan: ~5,249 rows and 725 KB once the
    | "SEMUA" marketing option existed. Printing was the reason it stayed
    | unpaginated, but the print sheet is its own route now and calls
    | getReportPrint() below, so the screen is free to page.
    */
    public function getReport(Request $request){
        return $this->buildReport($request, true);
    }

    /*
    | The same report, unpaginated, for /report/print.
    |
    | A printed monthly report has to contain every row — never just whichever page
    | happened to be on screen. Mirrors the Data-Macet/Print and
    | Transfer-Harian/Print endpoints.
    */
    public function getReportPrint(Request $request){
        return $this->buildReport($request, false);
    }

    private function buildReport(Request $request, bool $paginate){
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

        $total   = (clone $query)->count();
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 200));
        $page    = max(1, (int) $request->input('page', 1));

        /*
        | Order of the ATM book each marketing keeps — see setUrutanAtm(). Loans
        | nobody has numbered yet follow, newest first, which is exactly the old
        | order: until staff start numbering, the report reads as it always did.
        |
        | The numbers are per marketing, so an all-marketing report groups by
        | marketing first; otherwise every book's #1 would land together.
        */
        if(!$dataMarketing || $dataMarketing == 'SEMUA'){
            $query = $query->orderBy('MARKETING');
        }
        $query = $query->orderByRaw('URUTAN_ATM IS NULL')
            ->orderBy('URUTAN_ATM')
            ->orderByDesc('ID');
        if($paginate){
            $query = $query->forPage($page, $perPage);
        }

        $DB = $query->get(['ID','NAMA','MARKETING','NO_KREDIT','LUNAS_BRP','JANGKA_WAKTU','JUMLAH_PENGAJUAN','KASBON','URUTAN_ATM','CREATED_AT']);

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
                "MARKETING"     => $row->MARKETING,
                "URUTAN_ATM"    => $row->URUTAN_ATM === null ? null : (int) $row->URUTAN_ATM,
                "CICILAN_TOTAL" => $cicilan,
                "KASBON"        => $kasbon,
                // Rightmost TOTAL column: what this member owes this month.
                "TOTAL"         => $kasbon + $cicilan,
                "PROGRESS"      => $row->LUNAS_BRP . " / " . $row->JANGKA_WAKTU,
                "CREATED_AT"    => Carbon::parse($row->CREATED_AT)->translatedFormat('d F Y'),
            ];
        }

        /*
        | Totals are summed by the caller from the rows it received. On screen that
        | means the current page, and the footer says so; the print sheet is
        | unpaginated, so its footer is the true period total. Computing a
        | whole-period total here would need a second aggregate over the per-loan
        | MIN(NOMINAL) grouping, which isn't worth it for a figure the printed
        | report already gives correctly.
        */
        return response()->json([
            "data" => $data,
            "meta" => [
                "page"      => $paginate ? $page : 1,
                "per_page"  => $paginate ? $perPage : max(1,$total),
                "total"     => $total,
                "last_page" => $paginate ? (int) ceil(max(1,$total) / $perPage) : 1,
            ],
        ],200);
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
    | Set a loan's position in its marketing's ATM book, or clear it.
    |
    | Data entry, not a decision about money, so — like toggleHidden — no
    | administrator password. Duplicates are allowed on purpose: one member with
    | two loans sits at one place in the book.
    */
    public function setUrutanAtm(Request $request){
        $validator = Validator::make($request->all(),[
            'ID'         => ['required','integer'],
            'URUTAN_ATM' => ['nullable','integer','min:1','max:99999'],
        ],[
            'ID.required'        => 'Data kredit wajib dipilih',
            'URUTAN_ATM.integer' => 'Nomor urut ATM harus berupa angka',
            'URUTAN_ATM.min'     => 'Nomor urut ATM minimal 1',
            'URUTAN_ATM.max'     => 'Nomor urut ATM terlalu besar',
        ]);

        if($validator->fails()){
            return response()->json([
                "status"  => "error",
                "message" => $validator->errors()->first(),
            ],422);
        }

        $kredit = DB::table('kosada_kredit')->where('ID',$request->input('ID'));

        if(!(clone $kredit)->exists()){
            return response()->json([
                "status"  => "error",
                "message" => "Data kredit tidak ditemukan!",
            ],404);
        }

        // update() returns 0 when the value did not change, so existence is
        // checked above rather than inferred from the affected-row count.
        $urutan = $request->input('URUTAN_ATM');
        $kredit->update(['URUTAN_ATM' => $urutan === null || $urutan === '' ? null : (int) $urutan]);

        return response()->json([
            "status"  => "success",
            "message" => $urutan === null || $urutan === ''
                ? "Nomor urut ATM dihapus."
                : "Nomor urut ATM disimpan.",
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
