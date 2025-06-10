<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::get('/', [App\Http\Controllers\GoogleDriveController::class, 'index'])->name('google-drive.index');
Route::post('/google-drive/upload', [App\Http\Controllers\GoogleDriveController::class, 'upload'])->name('google-drive.upload');
