<?php

use App\Http\Controllers\UD84\Report as UD84_Report;
use App\Http\Controllers\UD84\Member as UD84_Member;
use App\Http\Controllers\UD84\MasterProduk as UD84_Master;
use App\Http\Controllers\UD84\Penjualan as UD84_Penjualan;
use App\Http\Controllers\UD84\Pesanan as UD84_Pesanan;
use App\Http\Controllers\UD84\Stock as UD84_Stocks;

use App\Http\Controllers\Kosada\Kredit as Kosada_Kredit;
use App\Http\Controllers\Kosada\Member as Kosada_Member;
use App\Http\Controllers\Kosada\Report as Kosada_Report;
use App\Http\Controllers\Kosada\Surat as Kosada_Surat;

use App\Http\Controllers\Clyfar\Result as Clyfar_Result;
use App\Http\Controllers\Clyfar\Account as Clyfar_Account;
use App\Http\Controllers\Clyfar\Psychological as Clyfar_Psychological;

use App\Http\Controllers\POS\Master as POS_Master;
use App\Http\Controllers\POS\Penjualan as POS_Penjualan;
use App\Http\Controllers\POS\Transaksi as POS_Transaksi;
use App\Http\Controllers\POS\Report as POS_Report;
use App\Http\Controllers\POS\Users as POS_Users;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Authenticate;
use App\Http\Middleware\CorsMiddleware;
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

// Clyfar - Account
Route::post('/Clyfar/Create-Token', [Clyfar_Account::class, 'createAccount']);
Route::post('/Clyfar/Authorize-Token', [Clyfar_Account::class, 'authorizeAccount']);
Route::post('/Clyfar/Register-Account', [Clyfar_Account::class, 'registerAccount']);
Route::post('/Clyfar/Log-Out', [Clyfar_Account:: class, 'logOut']);

// Clyfar - Test
Route::post('/Clyfar/Verify-Token', [Clyfar_Psychological::class, 'verifyToken']);
Route::post('/Clyfar/Test-Completion', [Clyfar_Psychological::class, 'postTest']);
Route::post('/Clyfar/Baum-Completion', [Clyfar_Psychological::class, 'postBaum']);

// Clyfar- Dashboard
Route::get('/Clyfar/Dashboard', [Clyfar_Result::class, 'getTestee']);
Route::post('/Clyfar/Create-Account', [Clyfar_Result::class, 'createTestee']);
Route::post('/Clyfar/Check-Testee', [Clyfar_Result::class, 'viewTestee']);
Route::post('/Clyfar/View-Result', [Clyfar_Result::class, 'viewResult']);

// Kosada
Route::get('/Kosada/Data-Kredit', [Kosada_Kredit::class,'getCustomerData']);
Route::post('/Kosada/Tambah-Kredit',[Kosada_Kredit::class,'addKredit']);
Route::post('/Kosada/Hapus-Kredit',[Kosada_Kredit::class, 'deleteKredit']);
Route::post('/Kosada/Status-Lunas', [Kosada_Kredit::class, 'setLunas']);
Route::get('/Kosada/Realisasi-Kredit', [Kosada_Kredit::class, 'getRealisasiKredit']);
Route::post('/Kosada/Kredit-Lunas',[Kosada_Kredit::class, 'setKreditLunas']);
Route::get('/Kosada/Detail-Kredit/{ID}',[Kosada_Kredit::class, 'postDetailKredit']);
Route::post('/Kosada/Ubah-Marketing',[Kosada_Kredit::class, 'ubahMarketing']);

// Kosada - Kasbon
Route::post('/Kosada/Tambah-Kasbon',[Kosada_Kredit::class, 'addKasbon']);

// Kosada - Member
Route::get('/Kosada/Semua-Member',[Kosada_Member::class,'getMember']);
Route::post('/Kosada/Tambah-Member',[Kosada_Member::class, 'addMember']);
Route::post('/Kosada/Update-Member',[Kosada_Member::class, 'updateMember']);
Route::post('/Kosada/Hapus-Member',[Kosada_Member::class, 'deleteMember']);

// Kosada - Surat Tugas
Route::get('/Kosada/Surat-Tugas',[Kosada_Surat::class, 'getSurat']);
Route::post('/Kosada/Surat-Tugas',[Kosada_Surat::class, 'postSurat']);
Route::get('/Kosada/Surat-Tugas/Lihat/{ID}', [Kosada_Surat::class, 'lihatSurat']);

// Kosada - Report
Route::post('/Kosada/Report',[Kosada_Report::class, 'getReport']);

// Layescent - Master Product
Route::get('/POS/List-Item', [POS_Master::class, 'getItem']);
Route::post('/POS/Create-Item', [POS_Master::class, 'createItem']);
Route::post('/POS/Update-Item', [POS_Master::class, 'updateItem']);
Route::post('/POS/Detail-Item', [POS_Master::class, 'detailItem']);
Route::post('/POS/Delete-Item', [POS_Master::class, 'deleteItem']);

Route::post('/POS/Update-Stock', [POS_Master::class, 'updateStock']);

// Layescent - Transaksi
Route::post('/POS/Post-Transaction', [POS_Penjualan::class, 'saveTransaction']);

// Layescent - Riwayat Transaksi
Route::post('/POS/Delete-Detail-Penjualan', [POS_Transaksi::class, 'deleteDetailPenjualan']);
Route::post('/POS/Riwayat-Penjualan', [POS_Transaksi::class, 'transaksiPenjualan']);

// Layescent - Report
Route::post('/POS/Report', [POS_Report::class, 'downloadReport']);

// Layescent - Users
Route::post('/POS/Check-Token', [POS_Users::class, 'checkToken']);
Route::get('/POS/Users', [POS_Users::class, 'getUsers']);
Route::post('/POS/Update-Users', [POS_Users::class, 'updateUsers']);

// UD84 - Katalog
Route::get('/UD84/Master-Produk/Katalog', [UD84_Master::class, 'katalogProduk']);

// UD84
Route::post('/UD84/Master-Produk/Single', [UD84_Master::class, 'singleItems']);
Route::get('/UD84/Master-Produk/Retrieve',[UD84_Master::class, 'getMasterProduct']);
Route::post('/UD84/Master-Produk/Retrieve/Member', [UD84_Master::class, 'getMemberMasterProduct']);
Route::post('/UD84/Master-Produk/Insert', [UD84_Master::class, 'postMasterProduct']);
Route::post('/UD84/Master-Produk/Update', [UD84_Master::class, 'updateMasterProduct']);
Route::post('/UD84/Master-Produk/Delete', [UD84_Master::class, 'deleteMasterProduct']);
Route::post('/UD84/Master-Produk/Upload-Gambar', [UD84_Master::class, 'imageUpload']);
Route::get('/UD84/Master-Produk/Satuan', [UD84_Master::class, 'getSatuan']);

// UD84 - Penjualan
Route::post('/UD84/Penjualan/Saving-Receipt', [UD84_Penjualan::class, 'postPenjualan']);

// UD84 - Pesanan
Route::post('/UD84/Pesanan/Retrieve', [UD84_Pesanan::class, 'getPesanan']);
Route::post('/UD84/Pesanan/Retrieve-Items', [UD84_Pesanan::class, 'getItems']);
Route::post('/UD84/Pesanan/Delete', [UD84_Pesanan::class, 'removeItem']);
Route::post('/UD84/Pesanan/Validate-Order', [UD84_Pesanan::class, 'validateItem']);
Route::post('/UD84/Penjualan/Order-Online', [UD84_Pesanan::class, 'postPesanan']);

// UD84 - Member
Route::get('/UD84/Member/Retrieve', [UD84_Member::class,'getMember']);
Route::post('/UD84/Member/Insert', [UD84_Member::class, 'postMember']);
Route::post('/UD84/Member/Delete', [UD84_Member::class, 'deleteMember']);
Route::post('/UD84/Member/Create-Sales', [UD84_Member::class, 'salesCreate']);

// UD84 - Report
Route::post('UD84/Charts/Password', [UD84_Report::class, 'confirmPassword']);
Route::get('/UD84/Charts',[UD84_Report::class, 'commonCharts']);
Route::get('/UD84/Omset/Single/{ID}',[UD84_Report::class, 'singleItemReport']);
Route::get('/UD84/Omset',[UD84_Report::class, 'omsetDetail']);
Route::post('/UD84/Operasional/Insert',[UD84_Report::class, 'postOperasional']);
Route::get('/UD84/Operasional/Retrieve',[UD84_Report::class, 'getReportOperasional']);
Route::post('/UD84/Reports/Sales', [UD84_Report::class, 'reportSales']);
Route::post('/UD84/Reports/Sales-Member', [UD84_Report::class, 'salesMember']);

// UD84 - Daftar Transaksi
Route::get('/UD84/Daftar-Transaksi', [UD84_Report::class,'daftarTransaksi']);
Route::post('/UD84/Daftar-Transaksi/Search', [UD84_Report::class,'searchTransaksi']);
Route::get('/UD84/Daftar-Transaksi/Detail-Transaksi/{ID}', [UD84_Report::class, 'detailTransaksi']);
Route::post('/UD84/Daftar-Transaksi/Update-DP', [UD84_Report::class, 'updateDP']);

Route::get('/UD84/Get-Invoices/{ID}', [UD84_Report::class, 'getInvoices']);
Route::post('/UD84/Reports/Single-Item', [UD84_Report::class, 'singleItem']);

// UD84 - Stocks 
Route::post('/UD84/Stocks/Dashboard', [UD84_Stocks::class, 'dashboard']);
Route::post('/UD84/Stocks/Detail', [UD84_Stocks::class, 'view']);
Route::post('/UD84/Stocks/Kartu', [UD84_Stocks::class, 'kartuStok']);

// UD84 - Stocks Input
Route::get('/UD84/Stocks/Staff', [UD84_Stocks::class, 'getUser']);
Route::post('/UD84/Stocks/Manipulate', [UD84_Stocks::class, 'stocksAdmin']);
