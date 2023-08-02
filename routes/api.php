<?php

use App\Http\Controllers\UD84\Report as UD84_Report;
use App\Http\Controllers\UD84\MasterProduk as UD84_Master;
use App\Http\Controllers\UD84\Penjualan as UD84_Penjualan;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Authenticate;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/Log-In', [Authenticate::class, 'logIn'])->name('login');
// Route::get('/Generate-Admin', [Authenticate::class, 'generateAdmin']);

Route::group(['middleware' => 'auth:sanctum'], function() {

    // UD84 - Alul
    Route::get('/UD84/Master-Produk/Retrieve',[UD84_Master::class, 'getMasterProduct']);
    Route::post('/UD84/Master-Produk/Insert', [UD84_Master::class, 'postMasterProduct']);
    Route::post('/UD84/Master-Produk/Update', [UD84_Master::class, 'updateMasterProduct']);
    Route::post('/UD84/Master-Produk/Delete', [UD84_Master::class, 'deleteMasterProduct']);

    // UD84 - Katalog
    Route::get('/UD84/Master-Produk/Katalog', [UD84_Master::class, 'katalogProduk']);

    // UD84 - Penjualan
    Route::post('/UD84/Penjualan/Saving-Receipt', [UD84_Penjualan::class, 'postPenjualan']);

    // UD84 - Report
    Route::get('/UD84/Daftar-Transaksi', [UD84_Report:: class,'daftarTransaksi']);
    Route::get('/UD84/Daftar-Transaksi/Detail-Transaksi/{ID}', [UD84_Report::class, 'detailTransaksi']);

    Route::get('/Status-Check', [Authenticate::class, 'whoAmI']);
});
