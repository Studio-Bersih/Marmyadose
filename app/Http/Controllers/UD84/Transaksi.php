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
     * Whether a sale's lines can be edited at all.
     *
     * Editing a line means re-adjusting stock, and that needs two things the
     * older rows do not have: a KODE that still resolves to a product, and a
     * SATUAN saying whether the line was sold loose or as a whole Set/Dus.
     * Without the unit the multiplier is a guess, wrong by JUMLAH_PER_ITEM --
     * commonly ten -- so such a sale gets header and money correction only.
     *
     * Returns [bool $boleh, ?string $alasan]; the reason names the first line
     * that blocks it, because "this sale cannot be edited" without saying why
     * is a dead end for whoever reads it.
     */
    public static function syaratUbahItem(string $unique): array
    {
        $lines = DB::table('ud84_penjualan_detail')->where('UNIQUE', $unique)->get();

        if ($lines->isEmpty()) {
            return [false, 'Transaksi ini tidak menyimpan rincian item, jadi itemnya tidak bisa diubah.'];
        }

        foreach ($lines as $line) {
            if (empty($line->KODE) || !DB::table('ud84_master_produk')->where('ID', $line->KODE)->exists()) {
                return [false, "Item '{$line->NAMA}' tidak terhubung ke produk yang masih ada, jadi stoknya tidak bisa dihitung ulang."];
            }

            if (empty($line->SATUAN)) {
                return [false, "Item '{$line->NAMA}' tidak mencatat satuan penjualan, jadi jumlah pcs-nya tidak bisa dipastikan."];
            }
        }

        return [true, null];
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

    private function gagal(string $pesan)
    {
        return response()->json([
            'status'  => 'error',
            'message' => $pesan,
        ], 200);
    }

    /**
     * Moves one member's balance by a delta, flooring at zero.
     *
     * UMUM is not a member -- it is what a sale with no named customer stores
     * -- so points neither leave it nor arrive at it.
     */
    private function geserPoin(string $nama, int $delta): array
    {
        $catatan = [];
        $nama    = trim($nama);

        if ($delta === 0 || $nama === '' || strtoupper($nama) === 'UMUM') {
            return $catatan;
        }

        $member = DB::table('ud84_member')->whereRaw('TRIM(NAMA) = ?', [$nama])->first();

        if (empty($member)) {
            $catatan[] = "Poin tidak diubah: member '{$nama}' tidak ditemukan.";

            return $catatan;
        }

        $saldo = (int) ($member->POINT ?? 0);
        $baru  = $saldo + $delta;

        if ($baru < 0) {
            $catatan[] = "Poin '{$nama}' hanya dikurangi {$saldo} dari ".abs($delta).' karena saldo tidak mencukupi.';
            $baru      = 0;
        } elseif ($delta > 0) {
            $catatan[] = "Poin '{$nama}' ditambah {$delta}.";
        } else {
            $catatan[] = "Poin '{$nama}' dikurangi ".abs($delta).'.';
        }

        DB::table('ud84_member')->where('ID', $member->ID)->update([
            'POINT'      => $baru,
            'UPDATED_AT' => now(),
        ]);

        return $catatan;
    }

    /**
     * Settles points against the corrected sale.
     *
     * The balance moves by the DIFFERENCE against what this sale already
     * granted, never by the whole amount -- otherwise a correction would grant
     * the points twice. When the customer name changes the sale's points move
     * with it, because points sitting with the wrong person are not fixable any
     * other way short of cancelling the sale.
     *
     * Sales predating the POIN column have it null; what they granted is
     * recomputed from their stored CASH, the same fallback cancellation uses.
     */
    private function selaraskanPoin(object $rekap, string $namaBaru, int $cashBaru): array
    {
        $perPoin  = (int) config('ud84.poin_per_rupiah');
        $poinBaru = ($cashBaru > 0 && $perPoin > 0) ? (int) floor($cashBaru / $perPoin) : 0;

        if ($rekap->POIN !== null) {
            $poinLama = (int) $rekap->POIN;
        } else {
            $cashLama = (int) ($rekap->CASH ?? 0);
            $poinLama = ($cashLama > 0 && $perPoin > 0) ? (int) floor($cashLama / $perPoin) : 0;
        }

        $namaLama = trim((string) ($rekap->NAMA ?? ''));
        $namaBaru = trim($namaBaru);

        if ($namaLama === $namaBaru) {
            $catatan = $this->geserPoin($namaBaru, $poinBaru - $poinLama);
        } else {
            $catatan = array_merge(
                $this->geserPoin($namaLama, -$poinLama),
                $this->geserPoin($namaBaru, $poinBaru)
            );
        }

        return ['POIN' => $poinBaru, 'CATATAN' => $catatan];
    }

    /**
     * Correcting a completed sale, in place. One UNIQUE stays one sale: the
     * receipt number is the customer's reference, and cancel-and-re-ring would
     * change it, move the money into today's revenue, and leave two rows in the
     * books for one corrected quantity.
     */
    public function perbaikiTransaksi(Request $request)
    {
        $kode     = trim((string) $request->input('KODE'));
        $alasan   = trim((string) $request->input('ALASAN'));
        $operator = trim((string) $request->input('OPERATOR'));

        if ($alasan === '') {
            return $this->gagal('Alasan perbaikan wajib diisi.');
        }

        $rekap = DB::table('ud84_penjualan_rekap')->where('UNIQUE', $kode)->first();

        if (empty($rekap)) {
            return $this->gagal('Transaksi tidak ditemukan.');
        }

        if ($rekap->STATUS === 'Dibatalkan') {
            return $this->gagal('Transaksi yang sudah dibatalkan tidak bisa diperbaiki.');
        }

        $namaBaru   = trim((string) $request->input('NAMA'));
        $namaBaru   = $namaBaru === '' ? 'UMUM' : $namaBaru;
        $keterangan = $request->input('KETERANGAN');
        $jatuhTempo = $request->input('JATUH_TEMPO');
        $jatuhTempo = ($jatuhTempo === '' || $jatuhTempo === null) ? null : $jatuhTempo;

        $cash     = (int) $request->input('CASH', 0);
        $dp       = (int) $request->input('DP', 0);
        $potongan = (int) $request->input('POTONGAN', 0);

        if ($cash < 0 || $dp < 0 || $potongan < 0) {
            return $this->gagal('Nominal tidak boleh minus.');
        }

        $detailLama = DB::table('ud84_penjualan_detail')->where('UNIQUE', $kode)->get();
        $totalBarang = 0;

        foreach ($detailLama as $line) {
            $totalBarang += (int) $line->HARGA_TERJUAL;
        }

        if ($potongan > $totalBarang) {
            return $this->gagal('Potongan tidak boleh melebihi total barang.');
        }

        $total = $totalBarang - $potongan;
        // Recomputed exactly as postPenjualan writes it at sale time, so a
        // correction does not introduce a new inconsistency. That stored figure
        // ignores DP and is already wrong for deposit sales; the nota derives
        // its own and does not read it.
        $kembalian = $cash <= 0 ? 0 : $cash - $total;

        $catatan = $this->ringkasPerbaikan($rekap, [
            'NAMA'        => $namaBaru,
            'KETERANGAN'  => $keterangan,
            'JATUH_TEMPO' => $jatuhTempo,
            'CASH'        => $cash,
            'DP'          => $dp,
            'POTONGAN'    => $potongan,
            'TOTAL'       => $total,
        ]);

        if (empty($catatan)) {
            return $this->gagal('Tidak ada perubahan untuk disimpan.');
        }

        DB::beginTransaction();

        try {
            $sebelum = json_encode(['rekap' => $rekap, 'detail' => $detailLama], JSON_UNESCAPED_UNICODE);

            $poin = $this->selaraskanPoin($rekap, $namaBaru, $cash);
            $catatan = array_merge($catatan, $poin['CATATAN']);

            DB::table('ud84_penjualan_rekap')->where('UNIQUE', $kode)->update([
                'NAMA'        => $namaBaru,
                'MEMBER'      => $namaBaru,
                'KETERANGAN'  => $keterangan,
                'JATUH_TEMPO' => $jatuhTempo,
                'CASH'        => $cash,
                'DP'          => $dp,
                'POTONGAN'    => $potongan,
                'TOTAL'       => $total,
                'KEMBALIAN'   => $kembalian,
                'POIN'        => $poin['POIN'],
                'UPDATED_AT'  => now(),
            ]);

            $sesudahRekap  = DB::table('ud84_penjualan_rekap')->where('UNIQUE', $kode)->first();
            $sesudahDetail = DB::table('ud84_penjualan_detail')->where('UNIQUE', $kode)->get();

            DB::table('ud84_transaksi_log')->insert([
                'UNIQUE_TRANSAKSI' => $kode,
                'AKSI'             => 'Perbaikan',
                'OPERATOR'         => $operator !== '' ? $operator : 'Tidak diketahui',
                'ALASAN'           => $alasan,
                'CATATAN_SISTEM'   => implode("\n", $catatan),
                'SEBELUM'          => $sebelum,
                'SESUDAH'          => json_encode(['rekap' => $sesudahRekap, 'detail' => $sesudahDetail], JSON_UNESCAPED_UNICODE),
                'CREATED_AT'       => now(),
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Transaksi berhasil diperbaiki.',
                'data'    => [
                    'CATATAN'    => $catatan,
                    'STOK_MINUS' => [],
                ],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::info($e);

            return $this->gagal('Perbaikan gagal disimpan, tidak ada perubahan yang tersimpan.');
        }
    }

    /**
     * The header and money half of the change list, built BEFORE anything is
     * written. An empty list is how an edit that changes nothing is caught.
     */
    private function ringkasPerbaikan(object $rekap, array $baru): array
    {
        $catatan = [];

        $teks = [
            'NAMA'        => 'Nama pelanggan',
            'KETERANGAN'  => 'Keterangan',
            'JATUH_TEMPO' => 'Jatuh tempo',
        ];

        foreach ($teks as $kolom => $label) {
            $lama = (string) ($rekap->$kolom ?? '');
            $isi  = (string) ($baru[$kolom] ?? '');

            if ($lama !== $isi) {
                $catatan[] = "{$label}: '{$lama}' -> '{$isi}'";
            }
        }

        $uang = [
            'CASH'     => 'Pembayaran tunai',
            'DP'       => 'DP',
            'POTONGAN' => 'Potongan',
            'TOTAL'    => 'Total',
        ];

        foreach ($uang as $kolom => $label) {
            $lama = (int) ($rekap->$kolom ?? 0);
            $isi  = (int) ($baru[$kolom] ?? 0);

            if ($lama !== $isi) {
                $catatan[] = "{$label}: Rp ".number_format($lama, 0, ',', '.').' -> Rp '.number_format($isi, 0, ',', '.');
            }
        }

        return $catatan;
    }
}
