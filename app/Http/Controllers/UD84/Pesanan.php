<?php

namespace App\Http\Controllers\UD84;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Log;
use DB;

class Pesanan extends Controller
{
    public function postPesanan(Request $request) {
        DB::beginTransaction();

        try {
            $unique = uniqid();
            $nama = $request->input('NAMA');
            $whatsApp = $request->input('WHATSAPP');
            $sales = $request->input('SALES');
            $carts = $request->input('CARTS');
            $notes = $request->input('NOTES');

            if(empty($carts)) {
                return response()->json([
                    "status"    => "error",
                    "message"   => "Tidak ada item di keranjang"
                ],200);
            }

            DB::table('ud84_pesanan_rekap')->insert([
                "NAMA"      => $nama,
                "WHATSAPP"  => $whatsApp,
                "SALES"     => $sales,
                "KODE"      => $unique,
                "CATATAN"   => $notes,
            ]);

            $useCarts = [];
            for($i = 0; $i < count($carts); $i++) {
                $useCarts[] = [
                    "KODE"      => $unique,
                    "KODE_ITEM" => $carts[$i]['ID'],
                    "JUMLAH"    => $carts[$i]['QUANTITY'],
                ];
            }

            DB::table('ud84_pesanan_detail')->insert($useCarts);

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Pesanan tersimpan!',
            ],200);
        } catch (\Throwable $e) {
            DB::rollback();
            Log::info($e);
            return response()->json([
                'status'    => 'error',
                'message'   => 'Ada kesalahan pada server.',
            ], 200);
        }
    }

    /**
     * Correcting an order rewrites nothing it does not have to. Lines are
     * reconciled in place: ud84_analisa_sales dates every line by
     * ud84_pesanan_detail.CREATED_AT, so deleting and re-inserting an untouched
     * line would move an old order's contribution into today, silently.
     */
    public function updatePesanan(Request $request)
    {
        $kode     = trim((string) $request->input('KODE'));
        $nama     = trim((string) $request->input('NAMA'));
        $whatsApp = trim((string) $request->input('WHATSAPP'));
        $catatan  = $request->input('CATATAN');
        $operator = trim((string) $request->input('OPERATOR'));
        $alasan   = trim((string) $request->input('ALASAN'));

        $rekap = DB::table('ud84_pesanan_rekap')->where('KODE', $kode)->first();

        if (empty($rekap)) {
            return $this->gagal('Pesanan tidak ditemukan.');
        }

        if (!empty($rekap->VALID)) {
            return $this->gagal('Pesanan yang sudah diverifikasi tidak bisa diubah.');
        }

        if ($nama === '' || $whatsApp === '') {
            return $this->gagal('Nama dan WhatsApp pelanggan wajib diisi.');
        }

        $items = $request->input('ITEMS');

        if (!is_array($items) || count($items) === 0) {
            return $this->gagal('Pesanan harus punya minimal satu item.');
        }

        $diminta    = [];
        $namaProduk = [];

        foreach ($items as $item) {
            $kodeItem = (int) ($item['KODE_ITEM'] ?? 0);
            $jumlah   = (int) ($item['JUMLAH'] ?? 0);
            $produk   = DB::table('ud84_master_produk')->where('ID', $kodeItem)->first(['ID', 'NAMA']);

            if (empty($produk)) {
                return $this->gagal("Produk dengan kode {$kodeItem} tidak ditemukan lagi.");
            }

            if (isset($diminta[$kodeItem])) {
                return $this->gagal("Produk '{$produk->NAMA}' muncul dua kali.");
            }

            if ($jumlah <= 0) {
                return $this->gagal("Jumlah item '{$produk->NAMA}' harus lebih dari nol.");
            }

            $diminta[$kodeItem]    = $jumlah;
            $namaProduk[$kodeItem] = $produk->NAMA;
        }

        $salesLama = $rekap->SALES === null ? null : (int) $rekap->SALES;
        $salesBaru = $this->bacaSales($request->input('SALES'));

        // An order that already names a deactivated salesperson keeps them --
        // history stays intact. Only a CHANGE to one is refused.
        if ($salesBaru !== null && $salesBaru !== $salesLama) {
            $orang = DB::table('ud84_sales')->where('ID', $salesBaru)->first(['NAMA', 'STATUS']);

            if (empty($orang)) {
                return $this->gagal('Sales tidak ditemukan.');
            }

            if ($orang->STATUS === 'Nonaktif') {
                return $this->gagal("Sales '{$orang->NAMA}' sudah nonaktif.");
            }
        }

        $detail = DB::table('ud84_pesanan_detail')->where('KODE', $kode)->get();

        // Older data (and the pre-existing postPesanan, which never
        // deduplicates) can leave two rows on one order for the same
        // product. Mapping lines by KODE_ITEM would silently collapse one of
        // them, so refuse the edit instead of guessing which row is real.
        $terlihat = [];

        foreach ($detail as $line) {
            $kodeItem = (int) $line->KODE_ITEM;

            if (isset($terlihat[$kodeItem])) {
                $nama = DB::table('ud84_master_produk')->where('ID', $kodeItem)->value('NAMA') ?? "Produk #{$kodeItem}";

                return $this->gagal("Pesanan ini punya dua baris untuk produk '{$nama}'. Hubungi admin sistem untuk merapikan datanya sebelum diubah.");
            }

            $terlihat[$kodeItem] = true;
        }

        $catatanSistem = $this->ringkasPerubahan($rekap, $detail, [
            'NAMA'     => $nama,
            'WHATSAPP' => $whatsApp,
            'CATATAN'  => $catatan,
            'SALES'    => $salesBaru,
        ], $diminta, $namaProduk);

        if (empty($catatanSistem)) {
            return $this->gagal('Tidak ada perubahan untuk disimpan.');
        }

        DB::beginTransaction();

        try {
            $sebelum = json_encode(['rekap' => $rekap, 'detail' => $detail], JSON_UNESCAPED_UNICODE);

            DB::table('ud84_pesanan_rekap')->where('KODE', $kode)->update([
                'NAMA'       => $nama,
                'WHATSAPP'   => $whatsApp,
                'SALES'      => $salesBaru,
                'CATATAN'    => $catatan,
                'UPDATED_AT' => now(),
            ]);

            $lama = [];

            foreach ($detail as $line) {
                $lama[(int) $line->KODE_ITEM] = $line;
            }

            foreach ($diminta as $kodeItem => $jumlah) {
                if (!isset($lama[$kodeItem])) {
                    DB::table('ud84_pesanan_detail')->insert([
                        'KODE'       => $kode,
                        'KODE_ITEM'  => $kodeItem,
                        'JUMLAH'     => $jumlah,
                        'CREATED_AT' => now(),
                    ]);

                    continue;
                }

                if ((int) $lama[$kodeItem]->JUMLAH !== $jumlah) {
                    // CREATED_AT is deliberately untouched -- see the note above.
                    DB::table('ud84_pesanan_detail')->where('ID', $lama[$kodeItem]->ID)->update([
                        'JUMLAH'     => $jumlah,
                        'UPDATED_AT' => now(),
                    ]);
                }
            }

            foreach ($lama as $kodeItem => $line) {
                if (!isset($diminta[$kodeItem])) {
                    DB::table('ud84_pesanan_detail')->where('ID', $line->ID)->delete();
                }
            }

            $sesudahRekap  = DB::table('ud84_pesanan_rekap')->where('KODE', $kode)->first();
            $sesudahDetail = DB::table('ud84_pesanan_detail')->where('KODE', $kode)->get();

            DB::table('ud84_transaksi_log')->insert([
                'UNIQUE_TRANSAKSI' => $kode,
                'AKSI'             => 'Edit Pesanan',
                'OPERATOR'         => $operator !== '' ? $operator : 'Tidak diketahui',
                'ALASAN'           => $alasan !== '' ? $alasan : null,
                'CATATAN_SISTEM'   => implode("\n", $catatanSistem),
                'SEBELUM'          => $sebelum,
                'SESUDAH'          => json_encode(['rekap' => $sesudahRekap, 'detail' => $sesudahDetail], JSON_UNESCAPED_UNICODE),
                'CREATED_AT'       => now(),
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Pesanan berhasil diperbarui.',
                'data'    => ['CATATAN' => $catatanSistem],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::info($e);

            return $this->gagal('Perubahan gagal disimpan, tidak ada yang berubah.');
        }
    }

    public function getPesanan(Request $request) {
        $startDate = $request->input('start');
        $endDate   = $request->input('end');

        // Validasi tanggal tidak kosong
        if (!$startDate || !$endDate) {
            return response()->json([
                "status"  => "error",
                "message" => "Harap masukkan tanggal awal dan akhir."
            ], 400);
        }

        // Pastikan endDate tidak lebih awal dari startDate
        if (strtotime($endDate) < strtotime($startDate)) {
            return response()->json([
                "status"  => "error",
                "message" => "Tanggal akhir tidak boleh lebih awal dari tanggal mulai."
            ], 400);
        }

        $DB = DB::table('ud84_pesanan_rekap')->where('CREATED_AT', '>=', $startDate)->where('CREATED_AT', '<=', $endDate)->orderByDesc('ID')->get();

        $useDB = [];
        foreach($DB as $DB) {
            // SALES is null for orders placed without a salesperson ("Tanpa
            // Sales" on the order form), and the row is gone if a salesperson
            // was ever deleted. Either way there is no name to show.
            $salesName = DB::table('ud84_sales')->where('ID', $DB->SALES)->first(['NAMA']);
            $useDB[] = [
                "NAMA"          => $DB->NAMA,
                "WHATSAPP"      => $DB->WHATSAPP,
                "SALES"         => $salesName->NAMA ?? '-',
                "SALES_ID"      => $DB->SALES === null ? null : (int) $DB->SALES,
                "CATATAN"       => $DB->CATATAN,
                "KODE"          => $DB->KODE,
                "VALID"         => $DB->VALID,
                "CREATED_AT"    => $DB->CREATED_AT
            ];
        }

        return response()->json([
            'status'    => 'success',
            'message'   => 'Loaded',
            'data'      => $useDB
        ],200);
    }

    public function getItems(Request $request) {
        $id = $request->input("ID");

        try {
            $DB = DB::table('ud84_pesanan_detail')->where('KODE', $id)->get(['KODE_ITEM', 'JUMLAH']);

            $useCarts = [];
            foreach($DB as $DB) {
                $findItem = DB::table('ud84_master_produk')->where('ID', $DB->KODE_ITEM)->first();

                // A product deleted since the order was placed used to throw
                // here, which made the order impossible even to open. The line
                // is reported as unresolvable instead, so it can be removed.
                if (empty($findItem)) {
                    $useCarts[] = [
                        "KODE_ITEM"         => (int) $DB->KODE_ITEM,
                        "ADA"               => false,
                        "NAMA"              => "Produk #{$DB->KODE_ITEM} tidak ditemukan",
                        "JUMLAH"            => (int) $DB->JUMLAH,
                        "STOK"              => 0,
                        "SATUAN"            => '-',
                        "HARGA_PER_ITEM"    => 0,
                        "HARGA_JUAL"        => 0,
                        "DISTRIBUTOR"       => '-'
                    ];
                    continue;
                }

                $useCarts[] = [
                    "KODE_ITEM"         => (int) $DB->KODE_ITEM,
                    "ADA"               => true,
                    "NAMA"              => $findItem->NAMA,
                    "JUMLAH"            => (int) $DB->JUMLAH,
                    "STOK"              => $findItem->STOK,
                    "SATUAN"            => $findItem->TIPE,
                    "HARGA_PER_ITEM"    => $findItem->HARGA_PER_ITEM,
                    "HARGA_JUAL"        => $findItem->HARGA_JUAL,
                    "DISTRIBUTOR"       => $findItem->DISTRIBUTOR
                ];
            }

            return response()->json([
                'status'    => 'success',
                'message'   => 'Loaded',
                'data'      => $useCarts
            ],200);
        } catch(\Throwable $e) {
            Log::info($e);
            return response()->json([
                "status"  => "error",
                "message" => "Ada kesalahan pada server."
            ], 500);
        }
    }

    /**
     * Deleting used to succeed against anything, verified or not, and left no
     * trace at all. A verified order feeds ud84_analisa_sales, so removing one
     * moves sales figures exactly as editing one would -- the same hole by
     * another door.
     */
    public function removeItem(Request $request){
        $kode     = trim((string) $request->input('ID'));
        $operator = trim((string) $request->input('OPERATOR'));
        $alasan   = trim((string) $request->input('ALASAN'));

        $rekap = DB::table('ud84_pesanan_rekap')->where('KODE', $kode)->first();

        if (empty($rekap)) {
            return $this->gagal('Pesanan tidak ditemukan.');
        }

        if (!empty($rekap->VALID)) {
            return $this->gagal('Pesanan yang sudah diverifikasi tidak bisa dihapus.');
        }

        DB::beginTransaction();

        try {
            $detail = DB::table('ud84_pesanan_detail')->where('KODE', $kode)->get();

            DB::table('ud84_transaksi_log')->insert([
                'UNIQUE_TRANSAKSI' => $kode,
                'AKSI'             => 'Hapus Pesanan',
                'OPERATOR'         => $operator !== '' ? $operator : 'Tidak diketahui',
                'ALASAN'           => $alasan !== '' ? $alasan : null,
                'CATATAN_SISTEM'   => 'Pesanan dihapus beserta '.count($detail).' item.',
                'SEBELUM'          => json_encode(['rekap' => $rekap, 'detail' => $detail], JSON_UNESCAPED_UNICODE),
                'SESUDAH'          => null,
                'CREATED_AT'       => now(),
            ]);

            DB::table('ud84_pesanan_detail')->where('KODE', $kode)->delete();
            DB::table('ud84_pesanan_rekap')->where('KODE', $kode)->delete();

            DB::commit();

            return response()->json([
                "status"  => "success",
                "message" => "Pesanan berhasil dihapus."
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::info($e);

            return $this->gagal('Pesanan gagal dihapus.');
        }
    }

    /**
     * The success message used to read "Pesanan berhasil dihapus." -- telling
     * the operator the opposite of what happened -- and re-verifying an
     * already-verified order was accepted silently.
     */
    public function validateItem(Request $request){
        $kode  = trim((string) $request->input('ID'));
        $rekap = DB::table('ud84_pesanan_rekap')->where('KODE', $kode)->first();

        if (empty($rekap)) {
            return $this->gagal('Pesanan tidak ditemukan.');
        }

        if (!empty($rekap->VALID)) {
            return $this->gagal('Pesanan ini sudah diverifikasi sebelumnya.');
        }

        DB::table('ud84_pesanan_rekap')->where('KODE', $kode)->update([
            "VALID"      => "Verified",
            "UPDATED_AT" => now(),
        ]);

        return response()->json([
            "status"  => "success",
            "message" => "Pesanan berhasil diverifikasi."
        ], 200);
    }

    public function salesHistory(Request $request) {
        try {
            $id = $request->input('ID');
            Log::info($id);
            $DB = DB::table('ud84_pesanan_rekap')
                ->where('SALES', $id)
                ->where('VALID', NULL)
                ->get(['KODE', 'CATATAN']);
    
            return response()->json([
                "status"  => "success",
                "message" => "Data berhasil dimuat!",
                "data"    => $DB
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"  => "error",
                "message" => "Terjadi kesalahan: " . $e->getMessage()
            ], 500);
        }
    }

    private function gagal(string $pesan)
    {
        return response()->json([
            'status'  => 'error',
            'message' => $pesan,
        ], 200);
    }

    /** "Tanpa Sales" arrives as null or an empty string; both mean no salesperson. */
    private function bacaSales($nilai): ?int
    {
        return ($nilai === null || $nilai === '') ? null : (int) $nilai;
    }

    private function namaSales(?int $id): string
    {
        if ($id === null) {
            return 'Tanpa Sales';
        }

        return DB::table('ud84_sales')->where('ID', $id)->value('NAMA') ?? "Sales #{$id}";
    }

    /**
     * A plain-language list of what this edit changes, built BEFORE anything is
     * written. It doubles as the check for an edit that changes nothing: an
     * empty list means there is nothing to save, and an audit trail full of
     * empty entries is worse than no entry.
     */
    private function ringkasPerubahan(object $rekap, $detail, array $baru, array $diminta, array $namaProduk): array
    {
        $catatan = [];

        $header = [
            'NAMA'     => 'Nama pelanggan',
            'WHATSAPP' => 'WhatsApp',
            'CATATAN'  => 'Keterangan',
        ];

        foreach ($header as $kolom => $label) {
            $lama = (string) ($rekap->$kolom ?? '');
            $isi  = (string) ($baru[$kolom] ?? '');

            if ($lama !== $isi) {
                $catatan[] = "{$label}: '{$lama}' -> '{$isi}'";
            }
        }

        $salesLama = $rekap->SALES === null ? null : (int) $rekap->SALES;

        if ($salesLama !== $baru['SALES']) {
            $catatan[] = "Sales: '".$this->namaSales($salesLama)."' -> '".$this->namaSales($baru['SALES'])."'";
        }

        $lamaItem = [];

        foreach ($detail as $line) {
            $lamaItem[(int) $line->KODE_ITEM] = (int) $line->JUMLAH;
        }

        foreach ($diminta as $kodeItem => $jumlah) {
            $nama = $namaProduk[$kodeItem];

            if (!isset($lamaItem[$kodeItem])) {
                $catatan[] = "Item '{$nama}' ditambahkan ({$jumlah})";
            } elseif ($lamaItem[$kodeItem] !== $jumlah) {
                $catatan[] = "Jumlah '{$nama}': {$lamaItem[$kodeItem]} -> {$jumlah}";
            }
        }

        foreach ($lamaItem as $kodeItem => $jumlah) {
            if (!isset($diminta[$kodeItem])) {
                $nama = DB::table('ud84_master_produk')->where('ID', $kodeItem)->value('NAMA') ?? "Produk #{$kodeItem}";
                $catatan[] = "Item '{$nama}' dihapus";
            }
        }

        return $catatan;
    }
}
