<?php

namespace App\Http\Controllers\Wedding;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class Comments extends Controller
{

    public function getComments(Request $request) {
        try {
            $DB = DB::table('wedding_comment')->where('FOR', $request->input('by'))->get();
            return response()->json([
                'status'    => 'success',
                'message'   => 'Berhasil mendapatkan data!',
                'data'      => $DB
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'    => 'error',
                'message'   => 'Oops! Silahkan coba lagi!',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    public function postComments(Request $request) {
        DB::beginTransaction();
        try {
            DB::table('wedding_comment')->insert([
                "FOR"       => $request->input('by'),
                "NAMA"      => $request->input('name'),
                "ATTEND"    => $request->input('attend'),
                "COMMENTS"  => $request->input('message')
            ]);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil meninggalkan pesan!',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Oops! Silahkan coba lagi!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
