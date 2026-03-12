<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConcorsiController;

Route::get('/', [ConcorsiController::class, 'index'])->name('home');
