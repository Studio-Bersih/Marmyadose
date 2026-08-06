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
     * Editing a line means re-adjusting stock, and that needs three things the
     * older rows do not always have: a KODE that still resolves to a product,
     * a SATUAN saying whether the line was sold loose or as a whole Set/Dus,
     * and -- when it was sold as a whole unit -- a product that still records
     * how many pieces that unit contains. Without any of these the multiplier
     * is a guess, wrong by JUMLAH_PER_ITEM -- commonly ten -- so such a sale
     * gets header and money correction only.
     *
     * The third check matters on its own: 114 of 409 products carry a null or
     * zero JUMLAH_PER_ITEM, so a non-Pcs line on one of them passes the first
     * two checks, opens the full item editor, and then has every save refused
     * by the old-line loop in perbaikiTransaksi -- an uncorrectable sale that
     * looks broken instead of routing to the header-only path it should take.
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
            if (empty($line->KODE)) {
                return [false, "Item '{$line->NAMA}' tidak terhubung ke produk yang masih ada, jadi stoknya tidak bisa dihitung ulang."];
            }

            $produk = DB::table('ud84_master_produk')->where('ID', $line->KODE)->first();

            if (empty($produk)) {
                return [false, "Item '{$line->NAMA}' tidak terhubung ke produk yang masih ada, jadi stoknya tidak bisa dihitung ulang."];
            }

            if (empty($line->SATUAN)) {
                return [false, "Item '{$line->NAMA}' tidak mencatat satuan penjualan, jadi jumlah pcs-nya tidak bisa dipastikan."];
            }

            if ($line->SATUAN !== 'Pcs' && (int) ($produk->JUMLAH_PER_ITEM ?? 0) <= 0) {
                return [false, "Item '{$line->NAMA}' tidak mencatat isi per satuan, jadi jumlah pcs-nya tidak bisa dipastikan."];
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
     * Pieces one line represents. STOK counts pieces, so a line sold as a whole
     * Set/Dus multiplies by JUMLAH_PER_ITEM.
     *
     * Returns null when the product does not record a per-item count and the
     * line is not loose -- the caller refuses rather than guessing, because
     * guessing is wrong by that very multiplier. Mirrors the $jumlah <= 0
     * early return in piecesUntukDikembalikan, its sibling on the cancellation
     * side, so the two agree on a zero-quantity line.
     */
    private function piecesBaris(string $satuan, int $jumlah, object $produk): ?int
    {
        if ($jumlah <= 0) {
            return 0;
        }

        if ($satuan === 'Pcs') {
            return $jumlah;
        }

        $perItem = (int) ($produk->JUMLAH_PER_ITEM ?? 0);

        return $perItem > 0 ? $jumlah * $perItem : null;
    }

    /**
     * Applies one net adjustment per product and records each on the stock card.
     *
     * $selisih maps product ID to the change in pieces sold: positive means more
     * goods left the shop, so STOK falls. Netting happens before this is called,
     * which is what makes a product moving between lines safe -- otherwise two
     * lines of one product would each apply their own adjustment and fight.
     *
     * Stock may end below zero. postPenjualan already subtracts without a floor,
     * so refusing here would block a correction that is probably right while
     * leaving the same outcome reachable through the POS; a negative figure is a
     * visible instruction to recount. Every such product is returned so the
     * operator can be told.
     *
     * The product row is read WITH lockForUpdate -- geserPoin locks a member row
     * for the identical reason: without it, two concurrent corrections of
     * DIFFERENT sales that both touch product P can each read the same starting
     * STOK and each write their own adjustment on top of it, and one vanishes.
     */
    private function terapkanStok(array $selisih): array
    {
        $catatan = [];
        $minus   = [];

        foreach ($selisih as $produkId => $delta) {
            if ($delta === 0) {
                continue;
            }

            $produk = DB::table('ud84_master_produk')->where('ID', $produkId)->lockForUpdate()->first();

            if (empty($produk)) {
                continue;
            }

            $stokAwal = (int) $produk->STOK;
            $stokBaru = $stokAwal - $delta;

            DB::table('ud84_master_produk')->where('ID', $produkId)->update([
                'STOK'       => $stokBaru,
                'UPDATED_AT' => now(),
            ]);

            DB::table('ud84_logs')->insert([
                'KODE_ITEM'  => $produkId,
                'NAMA_ITEM'  => $produk->NAMA,
                'ASAL'       => 'Perbaikan Transaksi',
                'MASUK'      => $delta < 0 ? abs($delta) : 0,
                'KELUAR'     => $delta > 0 ? $delta : 0,
                'STOK_FINAL' => $stokBaru,
                'CREATED_AT' => now(),
            ]);

            $arah      = $delta > 0 ? 'dikurangi' : 'ditambah';
            $catatan[] = "Stok '{$produk->NAMA}' {$arah} ".abs($delta)." pcs ({$stokAwal} -> {$stokBaru}).";

            if ($stokBaru < 0) {
                $minus[] = $produk->NAMA;
            }
        }

        return ['CATATAN' => $catatan, 'MINUS' => $minus];
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
     * -- so points neither leave it nor arrive at it. Locks the member row
     * (the caller is expected to already be inside a transaction) so two
     * concurrent corrections of the same sale cannot both read the same
     * starting balance and both apply their delta on top of it.
     *
     * Returns BERHASIL: whether the delta was actually applied to a real
     * balance. It is true for the trivial delta === 0 case (nothing needed
     * to happen) and false whenever the target cannot hold points at all --
     * UMUM, a blank name, or a name with no matching member -- because the
     * caller must not record a grant/deduction that never touched a ledger.
     */
    private function geserPoin(string $nama, int $delta): array
    {
        $catatan = [];
        $nama    = trim($nama);

        if ($nama === '' || strtoupper($nama) === 'UMUM') {
            return ['CATATAN' => $catatan, 'BERHASIL' => $delta === 0];
        }

        if ($delta === 0) {
            return ['CATATAN' => $catatan, 'BERHASIL' => true];
        }

        $member = DB::table('ud84_member')->whereRaw('TRIM(NAMA) = ?', [$nama])->lockForUpdate()->first();

        if (empty($member)) {
            $catatan[] = "Poin tidak diubah: member '{$nama}' tidak ditemukan.";

            return ['CATATAN' => $catatan, 'BERHASIL' => false];
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

        return ['CATATAN' => $catatan, 'BERHASIL' => true];
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
     *
     * The returned POIN is what was actually APPLIED, not merely computed --
     * when geserPoin could not touch a real balance (UMUM, blank, or a member
     * that no longer exists) the grant never happened, so POIN must not claim
     * otherwise. rekap.POIN is what cancellation later trusts to reverse; a
     * figure that was never granted would be taken back from whoever the
     * customer name resolves to by then, which may not even be the same
     * person.
     */
    private function selaraskanPoin(object $rekap, string $namaBaru, int $cashBaru): array
    {
        $perPoin  = (int) config('ud84.poin_per_rupiah');
        $poinBaru = ($cashBaru > 0 && $perPoin > 0) ? (int) floor($cashBaru / $perPoin) : 0;
        $catatan  = [];

        if ($rekap->POIN !== null) {
            $poinLama = (int) $rekap->POIN;
        } else {
            $cashLama = (int) ($rekap->CASH ?? 0);
            $poinLama = ($cashLama > 0 && $perPoin > 0) ? (int) floor($cashLama / $perPoin) : 0;

            if ($poinLama > 0) {
                $catatan[] = "Transaksi ini belum mencatat poin yang diberikan; jumlahnya dihitung ulang dari pembayaran tunai ({$poinLama} poin).";
            }
        }

        $namaLama = trim((string) ($rekap->NAMA ?? ''));
        $namaBaru = trim($namaBaru);

        if ($namaLama === $namaBaru) {
            $hasil     = $this->geserPoin($namaBaru, $poinBaru - $poinLama);
            $poinAkhir = $hasil['BERHASIL'] ? $poinBaru : $poinLama;
            $catatan   = array_merge($catatan, $hasil['CATATAN']);
        } else {
            $lama      = $this->geserPoin($namaLama, -$poinLama);
            $baru      = $this->geserPoin($namaBaru, $poinBaru);
            $poinAkhir = $baru['BERHASIL'] ? $poinBaru : 0;
            $catatan   = array_merge($catatan, $lama['CATATAN'], $baru['CATATAN']);
        }

        return ['POIN' => $poinAkhir, 'CATATAN' => $catatan];
    }

    /**
     * Correcting a completed sale, in place. One UNIQUE stays one sale: the
     * receipt number is the customer's reference, and cancel-and-re-ring would
     * change it, move the money into today's revenue, and leave two rows in the
     * books for one corrected quantity.
     *
     * The rekap row is read WITH lockForUpdate INSIDE the transaction, not
     * before it -- geserPoin does a read-modify-write on a member's POINT, and
     * two concurrent corrections of the same sale must not both read the same
     * starting POIN and both apply their delta on top of it. A sequential
     * double-submit is already safe because the second request sees the
     * already-updated row and is refused by the empty-change-list guard below;
     * only genuinely parallel requests need the lock.
     */
    public function perbaikiTransaksi(Request $request)
    {
        $kode     = trim((string) $request->input('KODE'));
        $alasan   = trim((string) $request->input('ALASAN'));
        $operator = trim((string) $request->input('OPERATOR'));

        if ($alasan === '') {
            return $this->gagal('Alasan perbaikan wajib diisi.');
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

        $items = $request->input('ITEMS');

        if ($items !== null) {
            // Validated before anything is written, so an edit the detail
            // screen would refuse never gets this far -- syaratUbahItem is the
            // single source of truth for whether a sale's lines can move.
            [$boleh, $alasanGate] = self::syaratUbahItem($kode);

            if (!$boleh) {
                return $this->gagal($alasanGate);
            }

            if (!is_array($items) || count($items) === 0) {
                return $this->gagal('Transaksi harus punya minimal satu item. Untuk mengosongkan, batalkan transaksinya.');
            }
        }

        DB::beginTransaction();

        try {
            // Refusing after this point still rolls back having written
            // nothing -- these are the same guards as before, just now able
            // to read a locked, currently-consistent row.
            $rekap = DB::table('ud84_penjualan_rekap')->where('UNIQUE', $kode)->lockForUpdate()->first();

            if (empty($rekap)) {
                DB::rollBack();

                return $this->gagal('Transaksi tidak ditemukan.');
            }

            if ($rekap->STATUS === 'Dibatalkan') {
                DB::rollBack();

                return $this->gagal('Transaksi yang sudah dibatalkan tidak bisa diperbaiki.');
            }

            $detailLama = DB::table('ud84_penjualan_detail')->where('UNIQUE', $kode)->get();

            $idSah       = $detailLama->pluck('ID')->map(fn ($id) => (int) $id)->all();
            $barisBaru   = [];
            $totalBarang = 0;

            if ($items === null) {
                // Header-only correction: the stored lines stand as they are.
                foreach ($detailLama as $line) {
                    $totalBarang += (int) $line->HARGA_TERJUAL;
                }
            } else {
                $idDipakai = [];

                foreach ($items as $item) {
                    $id       = isset($item['ID']) && $item['ID'] !== null ? (int) $item['ID'] : null;
                    $produkId = (int) ($item['KODE_ITEM'] ?? 0);
                    $satuan   = trim((string) ($item['SATUAN'] ?? ''));
                    $jumlah   = (int) ($item['JUMLAH'] ?? 0);
                    $harga    = (int) ($item['HARGA_ASLI'] ?? 0);
                    $persen   = (int) ($item['POTONGAN_PERSEN'] ?? 0);
                    $rupiah   = (int) ($item['POTONGAN_RUPIAH'] ?? 0);

                    if ($id !== null && !in_array($id, $idSah, true)) {
                        DB::rollBack();

                        return $this->gagal('Ada baris item yang bukan milik transaksi ini.');
                    }

                    // Keyed on the row ID, not the product -- two lines of one
                    // product are legitimate, this is what stops one stored row
                    // from being submitted twice under two payload entries.
                    if ($id !== null) {
                        if (isset($idDipakai[$id])) {
                            DB::rollBack();

                            return $this->gagal("Baris item dengan ID {$id} dikirim dua kali dalam permintaan ini.");
                        }

                        $idDipakai[$id] = true;
                    }

                    $produk = DB::table('ud84_master_produk')->where('ID', $produkId)->first();

                    if (empty($produk)) {
                        DB::rollBack();

                        return $this->gagal("Produk dengan kode {$produkId} tidak ditemukan.");
                    }

                    if ($satuan !== 'Pcs' && $satuan !== (string) ($produk->TIPE ?? '')) {
                        DB::rollBack();

                        return $this->gagal("Satuan '{$satuan}' tidak berlaku untuk produk '{$produk->NAMA}'.");
                    }

                    if ($jumlah <= 0) {
                        DB::rollBack();

                        return $this->gagal("Jumlah item '{$produk->NAMA}' harus lebih dari nol.");
                    }

                    if ($harga < 0 || $persen < 0 || $rupiah < 0) {
                        DB::rollBack();

                        return $this->gagal("Harga dan potongan item '{$produk->NAMA}' tidak boleh minus.");
                    }

                    $hargaSatuan = $harga - $persen - $rupiah;

                    if ($hargaSatuan < 0) {
                        DB::rollBack();

                        return $this->gagal("Potongan item '{$produk->NAMA}' melebihi harganya.");
                    }

                    $pieces = $this->piecesBaris($satuan, $jumlah, $produk);

                    if ($pieces === null) {
                        DB::rollBack();

                        return $this->gagal("Produk '{$produk->NAMA}' tidak mencatat isi per satuan, jadi stoknya tidak bisa dihitung.");
                    }

                    $barisBaru[] = [
                        'ID'              => $id,
                        'KODE'            => $produkId,
                        'NAMA'            => $produk->NAMA,
                        'SATUAN'          => $satuan,
                        'JUMLAH'          => $jumlah,
                        'HARGA_ASLI'      => $harga,
                        'POTONGAN_PERSEN' => $persen,
                        'POTONGAN_RUPIAH' => $rupiah,
                        'HARGA_TERJUAL'   => $hargaSatuan * $jumlah,
                        'PIECES'          => $pieces,
                    ];

                    $totalBarang += $hargaSatuan * $jumlah;
                }
            }

            if ($potongan > $totalBarang) {
                DB::rollBack();

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

            if ($items !== null) {
                $catatan = array_merge($catatan, $this->ringkasBaris($detailLama, $barisBaru));
            }

            if (empty($catatan)) {
                DB::rollBack();

                return $this->gagal('Tidak ada perubahan untuk disimpan.');
            }

            $sebelum = json_encode(['rekap' => $rekap, 'detail' => $detailLama], JSON_UNESCAPED_UNICODE);

            $stok = ['CATATAN' => [], 'MINUS' => []];

            if ($items !== null) {
                // Pieces per product, before and after, netted before anything is
                // written -- a product moving between lines is a return to one and
                // a withdrawal from the other, and two lines of one product must
                // not fight each other.
                $selisih = [];

                foreach ($detailLama as $line) {
                    $produkLama = DB::table('ud84_master_produk')->where('ID', $line->KODE)->first();

                    if (empty($produkLama)) {
                        continue;
                    }

                    $piecesLama = $this->piecesBaris((string) $line->SATUAN, (int) $line->JUMLAH, $produkLama);

                    if ($piecesLama === null) {
                        // Same refusal as the new-line path: guessing here would
                        // be wrong by JUMLAH_PER_ITEM, and syaratUbahItem's gate
                        // checks KODE and SATUAN but never JUMLAH_PER_ITEM.
                        DB::rollBack();

                        return $this->gagal("Produk '{$produkLama->NAMA}' tidak mencatat isi per satuan, jadi stoknya tidak bisa dihitung.");
                    }

                    $selisih[(int) $line->KODE] = ($selisih[(int) $line->KODE] ?? 0) - $piecesLama;
                }

                foreach ($barisBaru as $baris) {
                    $selisih[$baris['KODE']] = ($selisih[$baris['KODE']] ?? 0) + $baris['PIECES'];
                }

                $stok    = $this->terapkanStok($selisih);
                $catatan = array_merge($catatan, $stok['CATATAN']);

                $dipakai = [];

                foreach ($barisBaru as $baris) {
                    $isi = [
                        'KODE'            => $baris['KODE'],
                        'NAMA'            => $baris['NAMA'],
                        'SATUAN'          => $baris['SATUAN'],
                        'JUMLAH'          => $baris['JUMLAH'],
                        'HARGA_ASLI'      => $baris['HARGA_ASLI'],
                        'HARGA_TERJUAL'   => $baris['HARGA_TERJUAL'],
                        'POTONGAN_PERSEN' => $baris['POTONGAN_PERSEN'],
                        'POTONGAN_RUPIAH' => $baris['POTONGAN_RUPIAH'],
                        'UPDATED_AT'      => now(),
                    ];

                    if ($baris['ID'] === null) {
                        // CREATED_AT is the sale's own date, not today's -- the
                        // reports that bucket product/detail figures by
                        // CREATED_AT (Report::omsetDetail, singleItemReport,
                        // singleItem) join back to rekap for STATUS but not for
                        // the date, so a line dated today would report its
                        // quantity in this month's product totals while its
                        // money stays in the sale's own month's revenue --
                        // silently and permanently out of reconciliation. Same
                        // reasoning as leaving a surviving line's CREATED_AT
                        // untouched below.
                        DB::table('ud84_penjualan_detail')->insert(array_merge($isi, [
                            'UNIQUE'     => $kode,
                            'CREATED_AT' => $rekap->CREATED_AT,
                        ]));

                        continue;
                    }

                    // CREATED_AT deliberately untouched: it is the date this line
                    // reports under.
                    DB::table('ud84_penjualan_detail')->where('ID', $baris['ID'])->update($isi);
                    $dipakai[] = $baris['ID'];
                }

                foreach ($detailLama as $line) {
                    if (!in_array((int) $line->ID, $dipakai, true)) {
                        DB::table('ud84_penjualan_detail')->where('ID', $line->ID)->delete();
                    }
                }
            }

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
                    'STOK_MINUS' => $stok['MINUS'],
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

    /** The line half of the change list, in the operator's language. */
    private function ringkasBaris($detailLama, array $barisBaru): array
    {
        $catatan = [];
        $lama    = [];

        foreach ($detailLama as $line) {
            $lama[(int) $line->ID] = $line;
        }

        $dipakai = [];

        foreach ($barisBaru as $baris) {
            if ($baris['ID'] === null) {
                $catatan[] = "Item '{$baris['NAMA']}' ditambahkan ({$baris['JUMLAH']} {$baris['SATUAN']})";

                continue;
            }

            $dipakai[] = $baris['ID'];
            $asal      = $lama[$baris['ID']];

            if ((int) $asal->KODE !== $baris['KODE']) {
                $catatan[] = "Item '{$asal->NAMA}' diganti menjadi '{$baris['NAMA']}'";
            }

            if ((int) $asal->JUMLAH !== $baris['JUMLAH'] || (string) $asal->SATUAN !== $baris['SATUAN']) {
                $catatan[] = "Jumlah '{$baris['NAMA']}': {$asal->JUMLAH} {$asal->SATUAN} -> {$baris['JUMLAH']} {$baris['SATUAN']}";
            }

            if ((int) $asal->HARGA_TERJUAL !== $baris['HARGA_TERJUAL']) {
                $catatan[] = "Nilai '{$baris['NAMA']}': Rp ".number_format((int) $asal->HARGA_TERJUAL, 0, ',', '.')
                    .' -> Rp '.number_format($baris['HARGA_TERJUAL'], 0, ',', '.');
            }
        }

        foreach ($lama as $id => $line) {
            if (!in_array($id, $dipakai, true)) {
                $catatan[] = "Item '{$line->NAMA}' dihapus";
            }
        }

        return $catatan;
    }
}
