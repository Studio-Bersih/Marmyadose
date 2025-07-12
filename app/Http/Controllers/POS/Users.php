<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\DTO\Responses;
use Log;

class Users extends Controller
{
    public function checkToken(Request $request){
        $token = $request->input('token');
        $user = DB::table('pos_users')->where('TOKEN', $token)->first();

        if (empty($user)) {
            return response()->json(new Responses(
                "error", "Token tidak ditemukan!"
            ));
        }

        // Retrieve and group payment ranges by type name for the user's usaha
        $paymentData = DB::table('pos_payment_range_types as types')
            ->select(
                'types.ID as type_id',
                'types.NAME as type_name',
                'ranges.ID',
                'ranges.RANGE_START',
                'ranges.RANGE_END',
                'ranges.FEE',
                'ranges.USAHA'
            )
            ->leftJoin('pos_payment_ranges as ranges', 'ranges.TYPE_ID', '=', 'types.ID')
            ->where('types.USAHA', $user->USAHA)
            ->where(function ($query) use ($user) {
                $query->whereNull('ranges.USAHA')->orWhere('ranges.USAHA', $user->USAHA);
            })
            ->orderBy('types.ID')
            ->get();

        $groupedPaymentRanges = [];

        foreach ($paymentData as $entry) {
            if (!isset($groupedPaymentRanges[$entry->type_name])) {
                $groupedPaymentRanges[$entry->type_name] = [];
            }

            if ($entry->ID !== null) {
                $groupedPaymentRanges[$entry->type_name][] = [
                    'ID' => $entry->ID,
                    'RANGE_START' => $entry->RANGE_START,
                    'RANGE_END' => $entry->RANGE_END,
                    'FEE' => $entry->FEE,
                    'USAHA' => $entry->USAHA,
                ];
            }
        }

        return response()->json(new Responses(
            "success", "Authorized", [
                "token"     => $token,
                "roles"     => $user->ROLE,
                "usaha"     => $user->USAHA,
                "emoney"    => $groupedPaymentRanges,
            ]
        ));
    }

    public function getUsers(Request $request): JsonResponse {
        $DB = DB::table('pos_users')->where('USAHA', $request->input('usaha'))->get();
        return response()->json(new Responses(
            "success","Data berhasil dimuat", $DB
        ));
    }

    public function updateUsers(Request $request): JsonResponse {
        $id = $request->input('id');
        $token = $request->input('token');
        $cabang = $request->input('cabang');
        $usaha = $request->input('usaha');

        DB::table('pos_users')->where('ID', $id)->update([
            "TOKEN" => $token,
            "CABANG" => $cabang,
            "USAHA" => $usaha
        ]);

        return response()->json(new Responses(
            "success","Data berhasil diupdate!"
        ));
    }
}
