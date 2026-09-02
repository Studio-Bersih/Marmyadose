<?php

use App\Http\Controllers\UD84\Report as UD84_Report;
use App\Http\Controllers\UD84\Member as UD84_Member;
use App\Http\Controllers\UD84\MasterProduk as UD84_Master;
use App\Http\Controllers\UD84\Penjualan as UD84_Penjualan;
use App\Http\Controllers\UD84\Pesanan as UD84_Pesanan;
use App\Http\Controllers\UD84\Stock as UD84_Stocks;
use App\Http\Controllers\UD84\Sales as UD84_Sales;
use App\Http\Controllers\UD84\Transaksi as UD84_Transaksi;
use App\Http\Controllers\UD84\Poin as UD84_Poin;

use App\Http\Controllers\Kosada\Akun as Kosada_Akun;
use App\Http\Controllers\Kosada\Kredit as Kosada_Kredit;
use App\Http\Controllers\Kosada\Macet as Kosada_Macet;
use App\Http\Controllers\Kosada\Member as Kosada_Member;
use App\Http\Controllers\Kosada\Report as Kosada_Report;
use App\Http\Controllers\Kosada\Surat as Kosada_Surat;
use App\Http\Controllers\Kosada\Transfer as Kosada_Transfer;

use App\Http\Controllers\Clyfar\Result as Clyfar_Result;
use App\Http\Controllers\Clyfar\Account as Clyfar_Account;
use App\Http\Controllers\Clyfar\Psychological as Clyfar_Psychological;

use App\Http\Controllers\POS\Master as POS_Master;
use App\Http\Controllers\POS\Penjualan as POS_Penjualan;
use App\Http\Controllers\POS\Transaksi as POS_Transaksi;
use App\Http\Controllers\POS\Report as POS_Report;
use App\Http\Controllers\POS\Users as POS_Users;
use App\Http\Controllers\POS\EMoney as POS_E_Money;

use App\Http\Controllers\Wedding\Comments as Wedding_Comments;

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
Route::post('/Kosada/Realisasi-Kredit-Range', [Kosada_Kredit::class, 'getRealisasiKreditRange']);
Route::post('/Kosada/Kredit-Lunas',[Kosada_Kredit::class, 'setKreditLunas']);
Route::get('/Kosada/Detail-Kredit/{ID}',[Kosada_Kredit::class, 'postDetailKredit']);
Route::post('/Kosada/Ubah-Marketing',[Kosada_Kredit::class, 'ubahMarketing']);

// Kosada - Kasbon
Route::post('/Kosada/Tambah-Kasbon',[Kosada_Kredit::class, 'addKasbon']);

// Kosada - Member
Route::get('/Kosada/Semua-Member',[Kosada_Member::class,'getMember']);
Route::get('/Kosada/Cari-Member',[Kosada_Member::class,'cariMember']);
Route::post('/Kosada/Tambah-Member',[Kosada_Member::class, 'addMember']);
Route::post('/Kosada/Update-Member',[Kosada_Member::class, 'updateMember']);
Route::post('/Kosada/Hapus-Member',[Kosada_Member::class, 'deleteMember']);

// Kosada - Surat Tugas
Route::get('/Kosada/Surat-Tugas',[Kosada_Surat::class, 'getSurat']);
Route::post('/Kosada/Surat-Tugas',[Kosada_Surat::class, 'postSurat']);
Route::get('/Kosada/Surat-Tugas/Lihat/{ID}', [Kosada_Surat::class, 'lihatSurat']);

// Kosada - Report
Route::post('/Kosada/Report',[Kosada_Report::class, 'getReport']);
Route::post('/Kosada/Report/Print',[Kosada_Report::class, 'getReportPrint']);
Route::post('/Kosada/Sembunyikan-Laporan',[Kosada_Report::class, 'toggleHidden']);
Route::get('/Kosada/Laporan-Tersembunyi',[Kosada_Report::class, 'getHidden']);

// Kosada - Akun
Route::get('/Kosada/Akun',[Kosada_Akun::class, 'getAkun']);
Route::post('/Kosada/Tambah-Akun',[Kosada_Akun::class, 'addAkun']);
Route::post('/Kosada/Update-Akun',[Kosada_Akun::class, 'updateAkun']);
Route::post('/Kosada/Status-Akun',[Kosada_Akun::class, 'statusAkun']);
Route::post('/Kosada/Hapus-Akun',[Kosada_Akun::class, 'deleteAkun']);

// Kosada - Kredit Macet
Route::get('/Kosada/Data-Macet',[Kosada_Macet::class, 'getDataMacet']);
Route::get('/Kosada/Data-Macet/Print',[Kosada_Macet::class, 'printDataMacet']);
Route::post('/Kosada/Tambah-Macet',[Kosada_Macet::class, 'addMacet']);
Route::post('/Kosada/Selesai-Macet',[Kosada_Macet::class, 'selesaiMacet']);
Route::post('/Kosada/Status-Macet',[Kosada_Macet::class, 'statusMacet']);

// Kosada - Transfer Harian
Route::get('/Kosada/Transfer-Harian',[Kosada_Transfer::class, 'getTransferHarian']);
Route::get('/Kosada/Transfer-Harian/Print',[Kosada_Transfer::class, 'printTransferHarian']);
Route::get('/Kosada/Transfer-Harian/Kredit-Member/{memberID}',[Kosada_Transfer::class, 'getKreditMember']);
Route::post('/Kosada/Tambah-Transfer',[Kosada_Transfer::class, 'addTransfer']);
Route::post('/Kosada/Ubah-Transfer',[Kosada_Transfer::class, 'updateTransfer']);
Route::post('/Kosada/Hapus-Transfer',[Kosada_Transfer::class, 'deleteTransfer']);

// Layescent - Master Product
Route::post('/POS/Master-Product', [POS_Master::class, 'masterProduct']);
Route::post('/POS/List-Item', [POS_Master::class, 'getItem']);
Route::post('/POS/Create-Item', [POS_Master::class, 'createItem']);
Route::post('/POS/Update-Item', [POS_Master::class, 'updateItem']);
Route::post('/POS/Detail-Item', [POS_Master::class, 'detailItem']);
Route::post('/POS/Delete-Item', [POS_Master::class, 'deleteItem']);

// Layescent - Stock
Route::post('/POS/Update-Stock', [POS_Master::class, 'updateStock']);
Route::post('/POS/Item-Transfer', [POS_Master::class, 'itemTransfer']);
Route::post('/POS/Item-Masuk', [POS_Master::class, 'itemMasuk']);
Route::post('/POS/Item-Keluar', [POS_Master::class, 'itemKeluar']);

// Layescent - Transaksi
Route::post('/POS/Post-Transaction', [POS_Penjualan::class, 'saveTransaction']);

// Layescent - Riwayat Transaksi
Route::post('/POS/Delete-Detail-Penjualan', [POS_Transaksi::class, 'deleteDetailPenjualan']);
Route::post('/POS/Riwayat-Penjualan', [POS_Transaksi::class, 'transaksiPenjualan']);
Route::post('/POS/Tanggal-Penjualan', [POS_Transaksi::class, 'updateDate']);
Route::post('/POS/Logs', [POS_Transaksi::class, 'getLogs']);

// Layescent - Report
Route::post('/POS/Report', [POS_Report::class, 'downloadReport']);
Route::post('/POS/Report/EMoney', [POS_Report::class, 'getEmoneyReport']);
Route::post('/POS/Report/Monthly-Sales', [POS_Report::class, 'getMonthlySalesReport']);
Route::post('/POS/Report/Omset', [POS_Transaksi::class, 'trackKeuntungan']);

// Layescent - Users
Route::post('/POS/Check-Token', [POS_Users::class, 'checkToken']);
Route::post('/POS/Users', [POS_Users::class, 'getUsers']);
Route::post('/POS/Update-Users', [POS_Users::class, 'updateUsers']);

// Layescent - E-Money
Route::post('/POS/E-Money/Ranged', [POS_E_Money::class, 'getRanged']);
Route::post('/POS/E-Money/Ranged-Types', [POS_E_Money::class, 'getRangedTypes']);
Route::post('/POS/E-Money/Edit-Ranged-Types', [POS_E_Money::class, 'editMainRange']);
Route::post('/POS/E-Money/Delete-Ranged-Types', [POS_E_Money::class, 'deleteMainRange']);
Route::post('/POS/E-Money/Insert', [POS_E_Money::class, 'insertMoney']);
Route::post('/POS/E-Money/Insert-Type', [POS_E_Money::class, 'addType']);
Route::post('/POS/E-Money/Insert-Range', [POS_E_Money::class, 'addRange']);
Route::post('/POS/E-Money/View-Range', [POS_E_Money::class, 'viewRange']);
Route::post('/POS/E-Money/Update-Range', [POS_E_Money::class, 'updateRange']);
Route::post('/POS/E-Money/Delete-Range', [POS_E_Money::class, 'deleteRange']);

// Layescent - E-Money Management
Route::post('/POS/Report/Delete-EMoney', [POS_E_Money::class, 'deleteTransaction']);
Route::post('/POS/Report/Update-EMoney', [POS_E_Money::class, 'updateTransactionDate']);

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
Route::post('/UD84/Pesanan/Update', [UD84_Pesanan::class, 'updatePesanan']);

// Same reader as the transaction audit trail -- ud84_transaksi_log is keyed by
// UNIQUE_TRANSAKSI, which for an order is its KODE. Routed separately so the
// pesanan page is not calling a URL named after transactions.
Route::post('/UD84/Pesanan/Riwayat', [UD84_Transaksi::class, 'riwayatTransaksi']);

Route::post('/UD84/Penjualan/Order-Online', [UD84_Pesanan::class, 'postPesanan']);

// UD84 - Member
Route::get('/UD84/Member/Retrieve', [UD84_Member::class,'getMember']);
Route::post('/UD84/Member/Insert', [UD84_Member::class, 'postMember']);
Route::post('/UD84/Member/Delete', [UD84_Member::class, 'deleteMember']);
Route::post('/UD84/Member/Create-Sales', [UD84_Member::class, 'salesCreate']);

// UD84 - Poin Member
Route::get('/UD84/Poin/Retrieve', [UD84_Poin::class, 'getPoin']);
Route::post('/UD84/Poin/Adjust', [UD84_Poin::class, 'adjustPoin']);

// UD84 - Sales (manajemen tim sales)
Route::get('/UD84/Sales/Retrieve', [UD84_Sales::class, 'getSales']);
Route::post('/UD84/Sales/Insert', [UD84_Sales::class, 'postSales']);
Route::post('/UD84/Sales/Update', [UD84_Sales::class, 'updateSales']);
Route::post('/UD84/Sales/Delete', [UD84_Sales::class, 'deleteSales']);

// UD84 - Report
Route::post('UD84/Charts/Password', [UD84_Report::class, 'confirmPassword']);
Route::post('UD84/Charts/Sales-Password', [UD84_Report::class, 'salesPassword']);
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
Route::post('/UD84/Daftar-Transaksi/Batal', [UD84_Transaksi::class, 'batalTransaksi']);
Route::post('/UD84/Daftar-Transaksi/Riwayat', [UD84_Transaksi::class, 'riwayatTransaksi']);
Route::post('/UD84/Daftar-Transaksi/Perbaiki', [UD84_Transaksi::class, 'perbaikiTransaksi']);

Route::get('/UD84/Get-Invoices/{ID}', [UD84_Report::class, 'getInvoices']);
Route::post('/UD84/Reports/Single-Item', [UD84_Report::class, 'singleItem']);

// UD84 - Stocks 
Route::post('/UD84/Stocks/Dashboard', [UD84_Stocks::class, 'dashboard']);
Route::post('/UD84/Stocks/Detail', [UD84_Stocks::class, 'view']);
Route::post('/UD84/Stocks/Kartu', [UD84_Stocks::class, 'kartuStok']);

// UD84 - Sales
Route::post('/UD84/History-Sales', [UD84_Pesanan::class, 'salesHistory']);

// UD84 - Stocks Input
Route::get('/UD84/Stocks/Staff', [UD84_Stocks::class, 'getUser']);
Route::post('/UD84/Stocks/Manipulate', [UD84_Stocks::class, 'stocksAdmin']);

// UD84 - Auth
Route::post('/UD84/Auth', [Authenticate::class, 'logIn']);

// Wedding
Route::post('/Wedding/Get-Comments', [Wedding_Comments::class, 'getComments']);
Route::post('/Wedding/Post-Comments', [Wedding_Comments::class, 'postComments']);