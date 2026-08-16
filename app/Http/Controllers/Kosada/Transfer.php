<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use DB;

use App\Models\Kosada\TransferHarianModel;
use App\Models\Kosada\KreditModel;
use App\Models\Kosada\AdministratorModel;

class Transfer extends Controller
{
    private const JENIS = ['Kasbon','Top Up','Pinjaman Baru'];

    /*
    | See Macet::validateOrFail — $request->validate() renders a 302 HTML redirect
    | unless the caller sends `Accept: application/json`, and the frontend does not.
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
    | One day's transfers.
    |
    | Returns TANGGAL_TRANSFER and CREATED_AT separately so the page can flag rows
    | entered on a different day than the one they are recorded against. The
    | comparison is also done here as TERLAMBAT, so the print sheet doesn't have to
    | repeat the logic.
    */
    public function getTransferHarian(Request $request){
        $invalid = $this->validateOrFail($request,[
            'tanggal' => ['required','date'],
        ],[
            'tanggal.required' => 'Tanggal wajib diisi',
            'tanggal.date'     => 'Format tanggal tidak valid',
        ]);
        if($invalid) return $invalid;

        $tanggal = Carbon::parse($request->input('tanggal'))->toDateString();

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 500));
        $page    = max(1, (int) $request->input('page', 1));

        $query = TransferHarianModel::where('TANGGAL_TRANSFER',$tanggal);
        $total = (clone $query)->count();

        $records = $query->orderBy('ID')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'data'  => $this->buildRows($records),
            'meta'  => [
                'tanggal'   => $tanggal,
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) ceil(max(1,$total) / $perPage),
                'total_nominal' => (int) (clone $query)->sum('NOMINAL'),
            ],
        ],200);
    }

    /*
    | Same day, no pagination — the printed sheet must be the whole day.
    */
    public function printTransferHarian(Request $request){
        $invalid = $this->validateOrFail($request,[
            'tanggal' => ['required','date'],
        ],[
            'tanggal.required' => 'Tanggal wajib diisi',
        ]);
        if($invalid) return $invalid;

        $tanggal = Carbon::parse($request->input('tanggal'))->toDateString();
        $records = TransferHarianModel::where('TANGGAL_TRANSFER',$tanggal)->orderBy('ID')->get();

        return response()->json([
            'data' => $this->buildRows($records),
            'meta' => [
                'tanggal'       => $tanggal,
                'total'         => $records->count(),
                'total_nominal' => (int) $records->sum('NOMINAL'),
            ],
        ],200);
    }

    private function buildRows($records){
        return $records->map(function($row){
            $tanggalTransfer = Carbon::parse($row->TANGGAL_TRANSFER);
            $dibuat          = $row->CREATED_AT ? Carbon::parse($row->CREATED_AT) : null;

            /*
            | True when the row was entered on a different day than the one it is
            | recorded against — i.e. staff filled it in late. Not an error, but the
            | page renders these in red so management can spot them.
            */
            $terlambat = $dibuat !== null
                && $dibuat->toDateString() !== $tanggalTransfer->toDateString();

            return [
                'ID'                => $row->ID,
                'TANGGAL_TRANSFER'  => $tanggalTransfer->toDateString(),
                'NAMA'              => $row->NAMA,
                'INSTANSI'          => $row->INSTANSI ?: '-',
                'JENIS'             => $row->JENIS,
                'NOMINAL'           => (int) $row->NOMINAL,
                'KETERANGAN'        => $row->KETERANGAN,
                'TERLAMBAT'         => $terlambat,
                'DIINPUT_PADA'      => $dibuat ? $dibuat->translatedFormat('d F Y H:i') : null,
                'TANGGAL_TAMPIL'    => $tanggalTransfer->translatedFormat('d F Y'),
            ];
        })->values()->all();
    }

    /*
    | A member's active loans, with the figures the form auto-fills from.
    |
    | Kasbon        -> the loan's KASBON
    | Pinjaman Baru -> the loan's JUMLAH_PENGAJUAN
    | Top Up        -> manual, so no source here
    */
    public function getKreditMember(Request $request, $memberID){
        $loans = KreditModel::where('MEMBER_ID',$memberID)
            ->where('STATUS','Yes')
            ->orderByDesc('ID')
            ->get(['ID','NO_KREDIT','JUMLAH_PENGAJUAN','KASBON','JANGKA_WAKTU','CREATED_AT'])
            ->map(function($loan){
                return [
                    'ID'               => $loan->ID,
                    'JUMLAH_PENGAJUAN' => (int) $loan->JUMLAH_PENGAJUAN,
                    'KASBON'           => (int) $loan->KASBON,
                    'JANGKA_WAKTU'     => $loan->JANGKA_WAKTU,
                    'CREATED_AT'       => Carbon::parse($loan->CREATED_AT)->translatedFormat('d F Y'),
                ];
            });

        return response()->json($loans,200);
    }

    /*
    | Search members by name for the entry form.
    */
    public function cariMember(Request $request){
        $nama = $request->input('nama');

        if(empty($nama)){
            return response()->json([],200);
        }

        $members = AdministratorModel::where('NAMA','LIKE','%' . $nama . '%')
            ->orderBy('NAMA')
            ->limit(20)
            ->get(['ID','NAMA','PEKERJAAN','DATA_MARKETING'])
            ->map(function($m){
                return [
                    'ID'        => $m->ID,
                    'NAMA'      => $m->NAMA,
                    'PEKERJAAN' => $m->PEKERJAAN ?: '',
                    'MARKETING' => $m->DATA_MARKETING,
                ];
            });

        return response()->json($members,200);
    }

    public function addTransfer(Request $request){
        $invalid = $this->validateOrFail($request,[
            'TANGGAL_TRANSFER' => ['required','date'],
            'NAMA'             => ['required','string','max:255'],
            'JENIS'            => ['required','string','in:' . implode(',', self::JENIS)],
            'NOMINAL'          => ['required','numeric','min:0'],
            'MEMBER_ID'        => ['nullable','integer'],
            'KREDIT_ID'        => ['nullable','integer'],
            'INSTANSI'         => ['nullable','string','max:255'],
            'KETERANGAN'       => ['nullable','string','max:2000'],
        ],[
            'TANGGAL_TRANSFER.required' => 'Tanggal transfer wajib diisi',
            'NAMA.required'             => 'Nama nasabah wajib diisi',
            'JENIS.required'            => 'Jenis transfer wajib dipilih',
            'JENIS.in'                  => 'Jenis transfer harus Kasbon, Top Up, atau Pinjaman Baru',
            'NOMINAL.required'          => 'Nominal wajib diisi',
            'NOMINAL.numeric'           => 'Nominal harus berupa angka',
        ]);
        if($invalid) return $invalid;

        /*
        | CREATED_AT is set by the model's timestamps, never from the request. The
        | late-entry flag depends on it being the real insert time.
        */
        TransferHarianModel::create([
            'TANGGAL_TRANSFER' => Carbon::parse($request->input('TANGGAL_TRANSFER'))->toDateString(),
            'MEMBER_ID'        => $request->input('MEMBER_ID'),
            'KREDIT_ID'        => $request->input('KREDIT_ID'),
            'NAMA'             => $request->input('NAMA'),
            'INSTANSI'         => $request->input('INSTANSI'),
            'JENIS'            => $request->input('JENIS'),
            'NOMINAL'          => (int) $request->input('NOMINAL'),
            'KETERANGAN'       => $request->input('KETERANGAN'),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Data transfer berhasil disimpan!',
        ],200);
    }

    /*
    | Remove a mistaken entry. This is a recap sheet, so a wrong line should be
    | correctable; nothing else references these rows.
    */
    public function deleteTransfer(Request $request){
        $invalid = $this->validateOrFail($request,[
            'ID' => ['required','integer'],
        ],[
            'ID.required' => 'Data transfer wajib dipilih',
        ]);
        if($invalid) return $invalid;

        $deleted = TransferHarianModel::where('ID',$request->input('ID'))->delete();

        if($deleted === 0){
            return response()->json([
                'status'  => 'error',
                'message' => 'Data transfer tidak ditemukan!',
            ],404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Data transfer berhasil dihapus!',
        ],200);
    }
}
