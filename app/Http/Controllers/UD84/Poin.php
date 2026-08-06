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

    /**
     * Hand adjustment, the counter's half of the programme: a customer claims
     * something and the staff take the points off.
     *
     * Adding is an atomic increment. Subtracting is a CONDITIONAL decrement --
     * one statement that only matches a row whose balance is high enough -- so
     * two operators adjusting the same member at once cannot drive a balance
     * below zero between a read and a write, and nothing is clamped in
     * silence. With no record kept, a silent clamp would be unexplainable
     * afterwards.
     */
    public function adjustPoin(Request $request)
    {
        $id     = (int) $request->input('ID');
        $arah   = trim((string) $request->input('ARAH'));
        $jumlah = $request->input('JUMLAH');

        if (!is_numeric($jumlah) || (float) $jumlah != (int) $jumlah || (int) $jumlah < 1) {
            return $this->gagal('Jumlah poin harus berupa angka bulat minimal 1.');
        }

        $jumlah = (int) $jumlah;

        if ($arah !== 'Tambah' && $arah !== 'Kurang') {
            return $this->gagal('Pilih tambah atau kurang.');
        }

        $member = DB::table('ud84_member')->where('ID', $id)->first(['ID', 'NAMA', 'POINT']);

        if (empty($member)) {
            return $this->gagal('Member tidak ditemukan.');
        }

        try {
            if ($arah === 'Tambah') {
                $saldo = (int) ($member->POINT ?? 0);

                if ($saldo + $jumlah > self::BATAS_POIN) {
                    return $this->gagal('Poin melebihi batas maksimal '.self::BATAS_POIN.'.');
                }

                DB::table('ud84_member')->where('ID', $id)->increment('POINT', $jumlah, [
                    'UPDATED_AT' => now(),
                ]);
            } else {
                $terpengaruh = DB::table('ud84_member')
                    ->where('ID', $id)
                    ->where('POINT', '>=', $jumlah)
                    ->decrement('POINT', $jumlah, ['UPDATED_AT' => now()]);

                if ($terpengaruh === 0) {
                    $saldo = (int) DB::table('ud84_member')->where('ID', $id)->value('POINT');

                    return $this->gagal("Poin '{$member->NAMA}' hanya {$saldo}, tidak bisa dikurangi {$jumlah}.");
                }
            }

            $saldoBaru = (int) DB::table('ud84_member')->where('ID', $id)->value('POINT');

            return response()->json([
                'status'  => 'success',
                'message' => 'Poin berhasil diperbarui.',
                'data'    => ['POINT' => $saldoBaru],
            ], 200);
        } catch (\Throwable $e) {
            Log::info($e);

            return $this->gagal('Poin gagal diperbarui.');
        }
    }

    private function gagal(string $pesan)
    {
        return response()->json([
            'status'  => 'error',
            'message' => $pesan,
        ], 200);
    }
}
