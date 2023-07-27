<?php

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
    Route::get('/Status-Check', [Authenticate::class, 'whoAmI']);
});
