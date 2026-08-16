<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use DB;

use App\Http\Controllers\Kosada\Concerns\RequiresAdmin;
use App\Models\Kosada\KreditMacetModel;
use App\Models\Kosada\KreditModel;

class Macet extends Controller
{
    use RequiresAdmin;

    /*
    | Validate and hand back a ready-made JSON error, or null when the input is
    | fine.
    |
    | Deliberately not $request->validate(): that throws a ValidationException
    | which Laravel renders as a 302 redirect to an HTML page unless the caller
    | sent `Accept: application/json`. The Kosada frontend sends only
    | Content-Type, so it would receive HTML and blow up on .json(). Returning
    | JSON here does not depend on the caller getting its headers right.
    */
    private function validateOrFail(Request $request, array $rules, array $messages){
        $validator = Validator::make($request->all(), $rules, $messages);

        if($validator->fails()){
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ],422);
        }

        return null;
    }

    /*
    | Collection penalty applied to the outstanding balance of a bad loan.
    |
    | Kept as one named constant so the rate lives in exactly one place. The client
    | confirmed it recalculates live: if the borrower pays some installments down,
    | the penalty shrinks with the remainder.
    */
    private const PENALTI_RATE = 0.30;

    /*
    | Register a loan as macet.
    |
    | Only the reason is stored. All amounts are derived at read time -- see
    | buildRows() -- so the register cannot drift out of step with payments.
    */
    public function addMacet(Request $request){
        $invalid = $this->validateOrFail($request,[
            'KREDIT_ID'    => ['required','integer'],
            'ALASAN_MACET' => ['required','string','max:2000'],
        ],[
            'KREDIT_ID.required'    => 'Data kredit wajib dipilih',
            'ALASAN_MACET.required' => 'Alasan kredit macet wajib diisi',
            'ALASAN_MACET.max'      => 'Alasan kredit macet terlalu panjang',
        ]);
        if($invalid) return $invalid;

        $kredit = KreditModel::where('ID',$request->input('KREDIT_ID'))
            ->first(['ID','NO_KREDIT','MEMBER_ID','NAMA']);

        if(empty($kredit)){
            return response()->json([
                'status'  => 'error',
                'message' => 'Data kredit tidak ditemukan!',
            ],404);
        }

        $existing = KreditMacetModel::where('KREDIT_ID',$kredit->ID)->first();

        if(!empty($existing) && $existing->STATUS === 'Macet'){
            return response()->json([
                'status'  => 'error',
                'message' => $kredit->NAMA . ' sudah ada di data kredit macet.',
            ],409);
        }

        /*
        | updateOrCreate rather than create: the UNIQUE key on KREDIT_ID means a
        | previously resolved case cannot be re-inserted. Re-flagging reopens the
        | same row and clears the resolution fields.
        */
        KreditMacetModel::updateOrCreate(
            ['KREDIT_ID' => $kredit->ID],
            [
                'NO_KREDIT'       => $kredit->NO_KREDIT,
                'MEMBER_ID'       => $kredit->MEMBER_ID,
                'ALASAN_MACET'    => $request->input('ALASAN_MACET'),
                'STATUS'          => 'Macet',
                'TANGGAL_MACET'   => Carbon::now()->toDateString(),
                'TANGGAL_SELESAI' => null,
                'ALASAN_SELESAI'  => null,
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => $kredit->NAMA . ' berhasil ditambahkan ke kredit macet!',
        ],200);
    }

    /*
    | Close a case. The row stays -- the cooperative keeps its history.
    */
    public function selesaiMacet(Request $request){
        // Administrator only. Closing a case takes a loan off the collections
        // list -- a decision about money owed, not a data entry.
        if($denied = $this->requireAdmin($request)) return $denied;

        $invalid = $this->validateOrFail($request,[
            'ID'             => ['required','integer'],
            'ALASAN_SELESAI' => ['required','string','max:2000'],
        ],[
            'ID.required'             => 'Data macet wajib dipilih',
            'ALASAN_SELESAI.required' => 'Alasan penyelesaian wajib diisi',
        ]);
        if($invalid) return $invalid;

        $macet = KreditMacetModel::find($request->input('ID'));

        if(empty($macet)){
            return response()->json([
                'status'  => 'error',
                'message' => 'Data macet tidak ditemukan!',
            ],404);
        }

        $macet->STATUS          = 'Selesai';
        $macet->TANGGAL_SELESAI = Carbon::now()->toDateString();
        $macet->ALASAN_SELESAI  = $request->input('ALASAN_SELESAI');
        $macet->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Data macet ditandai selesai.',
        ],200);
    }

    /*
    | Paginated list for the screen.
    */
    public function getDataMacet(Request $request){
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 200));
        $page    = max(1, (int) $request->input('page', 1));

        $query = $this->baseQuery($request);
        $total = (clone $query)->count();

        $records = $query->orderByDesc('m.ID')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'data' => $this->buildRows($records),
            'meta' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) ceil(max(1,$total) / $perPage),
            ],
        ],200);
    }

    /*
    | Same filters, no pagination — the printed sheet must carry every row, not
    | just the page currently on screen.
    */
    public function printDataMacet(Request $request){
        $records = $this->baseQuery($request)->orderByDesc('m.ID')->get();
        return response()->json(['data' => $this->buildRows($records)],200);
    }

    /*
    | Left join throughout: a loan whose MEMBER_ID never resolved must still be
    | listed. An inner join would silently drop it.
    */
    private function baseQuery(Request $request){
        $query = DB::table('kosada_kredit_macet as m')
            ->join('kosada_kredit as k', 'k.ID', '=', 'm.KREDIT_ID')
            ->leftJoin('kosada_member as mb', 'mb.ID', '=', 'm.MEMBER_ID')
            ->select([
                'm.ID','m.KREDIT_ID','m.ALASAN_MACET','m.STATUS',
                'm.TANGGAL_MACET','m.TANGGAL_SELESAI','m.ALASAN_SELESAI',
                'k.NO_KREDIT','k.NAMA as KREDIT_NAMA','k.ALAMAT as KREDIT_ALAMAT',
                'k.MARKETING','k.JUMLAH_PENGAJUAN','k.KETERANGAN',
                'mb.NAMA as MEMBER_NAMA','mb.ALAMAT as MEMBER_ALAMAT',
                'mb.PEKERJAAN','mb.TELEPON',
            ]);

        if($request->filled('nama')){
            $nama = $request->input('nama');
            $query = $query->where('k.NAMA','LIKE','%' . $nama . '%');
        }

        $marketing = $request->input('marketing');
        if(!empty($marketing) && $marketing !== 'SEMUA'){
            $query = $query->where('k.MARKETING',$marketing);
        }

        // Defaults to open cases only; the page can ask for resolved or all.
        $status = $request->input('status','Macet');
        if(!empty($status) && $status !== 'SEMUA'){
            $query = $query->where('m.STATUS',$status);
        }

        return $query;
    }

    /*
    | Turn joined rows into the shape the page renders, computing every money
    | figure from live installment data.
    */
    private function buildRows($records){
        if($records->isEmpty()){
            return [];
        }

        /*
        | Outstanding balance per loan, in one query rather than one per row.
        | Only LUNAS = 'Belum' counts — that is what "sisa angsuran yang belum
        | dibayar" means.
        */
        $sisaPerKredit = DB::table('kosada_detail_kredit')
            ->whereIn('NO_KREDIT', $records->pluck('NO_KREDIT')->filter()->unique()->all())
            ->where('LUNAS','Belum')
            ->groupBy('NO_KREDIT')
            ->selectRaw('NO_KREDIT, SUM(NOMINAL) as SISA, SUM(COALESCE(KASBON,0)) as SISA_KASBON')
            ->get()
            ->keyBy('NO_KREDIT');

        return $records->map(function($row) use ($sisaPerKredit) {
            $sisaRow    = $sisaPerKredit[$row->NO_KREDIT] ?? null;
            $sisa       = (int) ($sisaRow->SISA ?? 0);
            $sisaKasbon = (int) ($sisaRow->SISA_KASBON ?? 0);

            // Three separate columns, as the client asked: the base, the penalty
            // on its own, and the total. Rounded to whole rupiah.
            $penalti = (int) round($sisa * self::PENALTI_RATE);

            return [
                'ID'                => $row->ID,
                'KREDIT_ID'         => $row->KREDIT_ID,
                'STATUS'            => $row->STATUS,

                // Member fields fall back to what the loan itself stored.
                'NAMA'              => $row->MEMBER_NAMA   ?: $row->KREDIT_NAMA,
                'ALAMAT'            => $row->MEMBER_ALAMAT ?: $row->KREDIT_ALAMAT,
                'PEKERJAAN'         => $row->PEKERJAAN ?: '-',
                'WHATSAPP'          => $row->TELEPON   ?: '-',

                'MARKETING'         => $row->MARKETING,
                'TOTAL_PINJAMAN'    => (int) $row->JUMLAH_PENGAJUAN,
                'SISA_ANGSURAN'     => $sisa,
                'PENALTI'           => $penalti,
                'TOTAL_TAGIHAN'     => $sisa + $penalti,
                'SISA_KASBON'       => $sisaKasbon,

                // The loan's own note, kept distinct from the macet reason below.
                'KETERANGAN'        => $row->KETERANGAN,
                'ALASAN_MACET'      => $row->ALASAN_MACET,

                'TANGGAL_MACET'     => $row->TANGGAL_MACET
                    ? Carbon::parse($row->TANGGAL_MACET)->translatedFormat('d F Y') : '-',
                'TANGGAL_SELESAI'   => $row->TANGGAL_SELESAI
                    ? Carbon::parse($row->TANGGAL_SELESAI)->translatedFormat('d F Y') : null,
                'ALASAN_SELESAI'    => $row->ALASAN_SELESAI,
            ];
        })->values()->all();
    }

    /*
    | Which of the given loan IDs are already registered, so the dashboard can
    | show a badge instead of the "add" button.
    */
    public function statusMacet(Request $request){
        $ids = $request->input('IDS', []);
        if(!is_array($ids) || empty($ids)){
            return response()->json([],200);
        }

        $rows = KreditMacetModel::whereIn('KREDIT_ID',$ids)
            ->where('STATUS','Macet')
            ->pluck('KREDIT_ID');

        return response()->json($rows,200);
    }
}
