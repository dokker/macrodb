<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->middleware('auth');

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');
