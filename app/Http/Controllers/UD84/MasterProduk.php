<?php

namespace App\Http\Controllers\UD84;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MasterProduk extends Controller
{
    public function getMasterProduct(){
        $data = DB::table('ud84_master_produk')->orderBy('NAMA')->get();
        return response()->json([
            "status"    => "success",
            "message"   => "Loaded",
            "data"      => $data
        ],200);
    }

    public function getMemberMasterProduct(Request $request){
        $ID = $request->input('ID');
        $memberData = DB::table('ud84_member')->where('ID',$ID)->first();
        $masterProdukCustom = DB::table('ud84_member_price')->where('UNIQUE',$memberData->UNIQUE)->get();

        $listData = [];
        foreach($masterProdukCustom as $data){
            $masterProduk = DB::table('ud84_master_produk')->where('NAMA',$data->NAMA)->first();
            $listData[] = [
                "NAMA"          => $masterProduk->NAMA,
                "STOK"          => $masterProduk->STOK,
                "TIPE"          => $masterProduk->TIPE,
                "HARGA_PABRIK"  => $masterProduk->HARGA_PABRIK,
                "HARGA_JUAL"    => $data->HARGA_JUAL,
            ];
        }
        return response()->json([
            'status'    => 'success',
            'message'   => 'Harga member berhasil dimuat!',
            'data'      => $listData
        ],200);
    }

    public function postMasterProduct(Request $request){
        DB::table('ud84_master_produk')->insert([
            "NAMA"              => $request->input('NAMA'),
            "STOK"              => $request->input('STOK'),
            "TIPE"              => $request->input('TIPE'),
            "STATUS_JUAL"       => $request->input('STATUS_JUAL'),
            "DISTRIBUTOR"       => $request->input('DISTRIBUTOR'),
            "HARGA_PABRIK"      => $request->input('HARGA_PABRIK'),
            "HARGA_JUAL"        => $request->input('HARGA_JUAL'),
            "JUMLAH_PER_ITEM"   => $request->input('JUMLAH_PER_ITEM'),
            "HARGA_PER_ITEM"    => $request->input('HARGA_PER_ITEM'),
            "DESKRIPSI"         => $request->input('DESKRIPSI')
        ]);
        return response()->json([
            'status'    => 'success',
            'message'   => 'Item berhasil disimpan!'
        ],200);
    }

    public function updateMasterProduct(Request $request){
        DB::table('ud84_master_produk')->where('ID', $request->input('ID') )->update([
            "NAMA"              => $request->input('NAMA'),
            "STOK"              => $request->input('STOK'),
            "TIPE"              => $request->input('TIPE'),
            "STATUS_JUAL"       => $request->input('STATUS_JUAL'),
            "DISTRIBUTOR"       => $request->input('DISTRIBUTOR'),
            "HARGA_PABRIK"      => $request->input('HARGA_PABRIK'),
            "HARGA_JUAL"        => $request->input('HARGA_JUAL'),
            "JUMLAH_PER_ITEM"   => $request->input('JUMLAH_PER_ITEM'),
            "HARGA_PER_ITEM"    => $request->input('HARGA_PER_ITEM'),
            "DESKRIPSI"         => $request->input('DESKRIPSI')
        ]);
        return response()->json([
            'status'    => 'success',
            'message'   => 'Item berhasil diudpate!'
        ],200);
    }

    public function deleteMasterProduct(Request $request){
        DB::table('ud84_master_produk')->where('ID', $request->input('ID'))->delete();
        return response()->json([
            'status'    => 'success',
            'message'   => 'Item berhasil dihapus!'
        ],200);
    }

    public function imageUpload(Request $request){
        $file   = $request->file('GAMBAR');
        $ID     = $request->input('ID');

        $fileName = $file->hashName();
        $file->move(public_path('UD84/Images/'), $fileName);

        DB::table('ud84_master_produk')->where('ID',$ID)->update([
            "GAMBAR"    => $fileName
        ]);

        return response()->json([
            'status'    => 'success',
            'message'   => 'Data berhasil disimpan.',
        ],200);
    }

    public function katalogProduk(){
        $data = DB::table('ud84_master_produk')->where('STATUS_JUAL','Katalog dan Penjualan')->get();
        $listProduk = [];
        foreach($data as $data){
            $listProduk[] = [
                "NAMA_PRODUK"           => $data->NAMA,
                "KETERANGAN"            => $data->DESKRIPSI,
                "KETERSEDIAAN_PRODUK"   => $data->STOK >= 0 ? 'Available' : 'Sold Out',
                "GAMBAR"                => $data->GAMBAR
            ];
        }
        return response()->json($listProduk,200);
    }

    public function singleItems(Request $request) {
        $productName   = $request->input('productName');

        if(is_numeric($productName)) {
            $data = DB::table('ud84_master_produk')->where('STATUS_JUAL','!=','Tidak Aktif')->where('ID',$productName)
            ->first(['ID', 'NAMA', 'STOK', 'TIPE', 'HARGA_PER_ITEM', 'JUMLAH_PER_ITEM', 'DISTRIBUTOR', 'DESKRIPSI']);

            if ($data) {
                $data = [
                    'ID'    => $data->ID,
                    'NAMA'  => $data->NAMA,
                    'STOK'  => $data->STOK,
                    'SATUAN'=> $data->TIPE,
                    'HARGA' => $data->HARGA_PER_ITEM,
                    'JUMLAH_PER_ITEM'=> $data->JUMLAH_PER_ITEM,
                    'DISTRIBUTOR' => $data->DISTRIBUTOR,
                    'INPUT_STOK' => 1,
                    'DESKRIPSI' => $data->DESKRIPSI,
                ];
            }

            return response()->json([
                "status"    => "success",
                "message"   => "Data ditemukan",
                "data"      => $data
            ],200);
        }

        $data = DB::table('ud84_master_produk')->where('STATUS_JUAL','!=','Tidak Aktif')->where('NAMA','LIKE','%' . $productName . '%')
        ->get(['ID', 'NAMA', 'STOK', 'TIPE', 'HARGA_PER_ITEM', 'JUMLAH_PER_ITEM','DISTRIBUTOR', 'DESKRIPSI'])
        ->map(function($item) {
            return [
                'ID'    => $item->ID,
                'NAMA'  => $item->NAMA,
                'STOK'  => $item->STOK,
                'SATUAN'=> $item->TIPE,
                'HARGA' => $item->HARGA_PER_ITEM,
                'JUMLAH_PER_ITEM'=> $item->JUMLAH_PER_ITEM,
                'DISTRIBUTOR' => $item->DISTRIBUTOR,
                'INPUT_STOK' => 1,
                'DESKRIPSI' => $item->DESKRIPSI,
            ];
        });

        return response()->json([
            "status"    => "error",
            "message"   => "Data tidak ditemukan",
            "data"      => $data
        ],200);
    }
}