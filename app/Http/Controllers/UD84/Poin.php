<?php

namespace App\Http\Controllers\UD84;

use DB;
use Log;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Member loyalty points.
 *
 * Points are earned automatically at checkout -- see Penjualan::postPenjualan
 * and config('ud84.poin_per_rupiah') -- and reversed by cancelling or
 * correcting a sale. This controller covers the other half: seeing what
 * members have, and adjusting it by hand when someone claims something at the
 * counter.
 *
 * Adjustments are deliberately not recorded. The owner chose a screen that
 * stays simple over one that keeps a trail; the consequence, that a disputed
 * balance has nothing to check against, is documented in the design.
 */
class Poin extends Controller
{
    /** The smallint ceiling on ud84_member.POINT. */
    private const BATAS_POIN = 32767;

    public function getPoin()
    {
        $member = DB::table('ud84_member')
            ->orderByDesc('POINT')
            ->orderBy('NAMA')
            ->get(['ID', 'NAMA', 'LOKASI', 'WHATSAPP', 'POINT']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Loaded',
            'data'    => [
                'MEMBER' => $member,
                'TOTAL'  => (int) DB::table('ud84_member')->sum('POINT'),
            ],
        ], 200);
    }
}
