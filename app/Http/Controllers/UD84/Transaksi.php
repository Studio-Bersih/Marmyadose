<?php

namespace App\Http\Controllers\UD84;

use DB;
use Log;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Transaksi extends Controller
{
    /**
     * Cancelling a sale voids it, returns its goods to stock and takes back the
     * points it granted. It deliberately does NOT touch CASH, DP, TOTAL or
     * POTONGAN -- money that changed hands is a historical fact, and refunding
     * it is a business action outside this system. STATUS records the void.
     */
    public function batalTransaksi(Request $request)
    {
        $kode     = trim((string) $request->input('KODE'));
        $alasan   = trim((string) $request->input('ALASAN'));
        $operator = trim((string) $request->input('OPERATOR'));

        if ($alasan === '') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Alasan pembatalan wajib diisi.',
            ], 200);
        }

        $rekap = DB::table('ud84_penjualan_rekap')->where('UNIQUE', $kode)->first();

        if (empty($rekap)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Transaksi tidak ditemukan.',
            ], 200);
        }

        if ($rekap->STATUS === 'Dibatalkan') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Transaksi ini sudah dibatalkan sebelumnya.',
            ], 200);
        }

        DB::beginTransaction();

        try {
            $detail  = DB::table('ud84_penjualan_detail')->where('UNIQUE', $kode)->get();
            $sebelum = json_encode(['rekap' => $rekap, 'detail' => $detail], JSON_UNESCAPED_UNICODE);

            $catatan     = [];
            $gagalRestok = [];

            foreach ($detail as $line) {
                $produk = $this->resolveProduk($line);

                if (empty($produk)) {
                    $gagalRestok[] = $line->NAMA;
                    $catatan[]     = "Stok '{$line->NAMA}' tidak dikembalikan: produk tidak ditemukan lagi di master produk.";
                    continue;
                }

                $pieces = $this->piecesUntukDikembalikan($line, $produk);

                if ($pieces === null) {
                    $gagalRestok[] = $line->NAMA;
                    $catatan[]     = "Stok '{$line->NAMA}' tidak dikembalikan: satuan penjualan tidak tercatat, jumlah pieces tidak bisa dipastikan.";
                    continue;
                }

                $stokAwal = (int) $produk->STOK;
                $stokBaru = $stokAwal + $pieces;

                DB::table('ud84_master_produk')->where('ID', $produk->ID)->update([
                    'STOK'       => $stokBaru,
                    'UPDATED_AT' => now(),
                ]);

                // A new reversing row, never an edit or delete of the sale's
                // original entry. The stock card should read as a history.
                DB::table('ud84_logs')->insert([
                    'KODE_ITEM'  => $produk->ID,
                    'NAMA_ITEM'  => $produk->NAMA,
                    'ASAL'       => 'Batal Transaksi',
                    'MASUK'      => $pieces,
                    'KELUAR'     => 0,
                    'STOK_FINAL' => $stokBaru,
                    'CREATED_AT' => now(),
                ]);

                $catatan[] = "Stok '{$produk->NAMA}' dikembalikan {$pieces} pcs ({$stokAwal} -> {$stokBaru}).";
            }

            $catatan = array_merge($catatan, $this->kembalikanPoin($rekap));

            DB::table('ud84_penjualan_rekap')->where('UNIQUE', $kode)->update([
                'STATUS'     => 'Dibatalkan',
                'UPDATED_AT' => now(),
            ]);

            $sesudah = DB::table('ud84_penjualan_rekap')->where('UNIQUE', $kode)->first();

            DB::table('ud84_transaksi_log')->insert([
                'UNIQUE_TRANSAKSI' => $kode,
                'AKSI'             => 'Batal',
                'OPERATOR'         => $operator !== '' ? $operator : 'Tidak diketahui',
                'ALASAN'           => $alasan,
                'CATATAN_SISTEM'   => implode("\n", $catatan),
                'SEBELUM'          => $sebelum,
                'SESUDAH'          => json_encode(['rekap' => $sesudah], JSON_UNESCAPED_UNICODE),
                'CREATED_AT'       => now(),
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Transaksi berhasil dibatalkan.',
                'data'    => [
                    'CATATAN'      => $catatan,
                    'GAGAL_RESTOK' => $gagalRestok,
                ],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::info($e);

            return response()->json([
                'status'  => 'error',
                'message' => 'Pembatalan gagal, tidak ada perubahan yang tersimpan.',
            ], 200);
        }
    }

    /** Audit trail for one transaction, newest first. */
    public function riwayatTransaksi(Request $request)
    {
        try {
            $rows = DB::table('ud84_transaksi_log')
                ->where('UNIQUE_TRANSAKSI', $request->input('KODE'))
                ->orderByDesc('ID')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Loaded',
                'data'    => $rows,
            ], 200);
        } catch (\Throwable $e) {
            Log::info($e);

            return response()->json([
                'status'  => 'error',
                'message' => 'Ada kesalahan pada server.',
            ], 200);
        }
    }

    /**
     * KODE is the reliable reference for sales written after this sub-project.
     * Older rows have it empty because postPenjualan always matched by name, so
     * NAMA is the fallback -- and for products since deleted, neither resolves.
     */
    private function resolveProduk(object $line): ?object
    {
        if (!empty($line->KODE)) {
            $byId = DB::table('ud84_master_produk')->where('ID', $line->KODE)->first();

            if (!empty($byId)) {
                return $byId;
            }
        }

        return DB::table('ud84_master_produk')->where('NAMA', $line->NAMA)->first();
    }

    /**
     * How many pieces to put back. STOK is counted in pieces, so a line sold as
     * a whole Set/Dus has to be multiplied by JUMLAH_PER_ITEM.
     *
     * Returns null when the answer cannot be known -- a line with no recorded
     * SATUAN, or a product with no per-item count. Guessing here would be wrong
     * by a factor of JUMLAH_PER_ITEM (commonly 10) and would corrupt stock
     * silently, so the caller skips the line and reports it instead.
     */
    private function piecesUntukDikembalikan(object $line, object $produk): ?int
    {
        $jumlah = (int) $line->JUMLAH;

        if ($jumlah <= 0) {
            return 0;
        }

        if ($line->SATUAN === 'Pcs') {
            return $jumlah;
        }

        if (!empty($line->SATUAN)) {
            $perItem = (int) ($produk->JUMLAH_PER_ITEM ?? 0);

            return $perItem > 0 ? $jumlah * $perItem : null;
        }

        return null;
    }

    /**
     * Reverses exactly what the sale granted, read from rekap.POIN. Sales made
     * before that column existed fall back to recomputing from CASH.
     *
     * The balance floors at zero: if the member has spent points since, the
     * full amount cannot be taken back, and that is recorded rather than
     * driving the balance negative.
     */
    private function kembalikanPoin(object $rekap): array
    {
        $catatan = [];
        $nama    = trim((string) ($rekap->NAMA ?? ''));

        if ($nama === '' || $nama === 'UMUM') {
            return $catatan;
        }

        if ($rekap->POIN !== null) {
            $poinDiberikan = (int) $rekap->POIN;
        } else {
            $cash          = (int) ($rekap->CASH ?? 0);
            $perPoin       = (int) config('ud84.poin_per_rupiah');
            $poinDiberikan = ($cash > 0 && $perPoin > 0) ? (int) floor($cash / $perPoin) : 0;

            if ($poinDiberikan > 0) {
                $catatan[] = "Transaksi ini belum mencatat poin yang diberikan; jumlahnya dihitung ulang dari pembayaran tunai ({$poinDiberikan} poin).";
            }
        }

        if ($poinDiberikan <= 0) {
            return $catatan;
        }

        $member = DB::table('ud84_member')->whereRaw('TRIM(NAMA) = ?', [$nama])->first();

        if (empty($member)) {
            $catatan[] = "Poin tidak dikurangi: member '{$nama}' tidak ditemukan.";

            return $catatan;
        }

        $saldo     = (int) ($member->POINT ?? 0);
        $dikurangi = min($poinDiberikan, max(0, $saldo));

        DB::table('ud84_member')->where('ID', $member->ID)->update([
            'POINT'      => $saldo - $dikurangi,
            'UPDATED_AT' => now(),
        ]);

        if ($dikurangi < $poinDiberikan) {
            $catatan[] = "Poin '{$nama}' hanya dikurangi {$dikurangi} dari {$poinDiberikan} karena saldo tidak mencukupi.";
        } else {
            $catatan[] = "Poin '{$nama}' dikurangi {$dikurangi}.";
        }

        return $catatan;
    }
}
