<?php

use App\Http\Controllers\ConversionController;
use App\Http\Controllers\ConverterController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ConverterController::class, 'index'])->name('home');

Route::get('/formats', [ConverterController::class, 'formats'])->name('formats.index');

Route::post('/conversions', [ConversionController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('conversions.store');

Route::get('/conversions/{token}', [ConversionController::class, 'download'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->name('conversions.download');

Route::get('/{from}-{to}', [ConverterController::class, 'show'])
    ->where([
        'from' => 'pdf|docx|jpg|jpeg|png',
        'to' => 'pdf|docx|jpg|jpeg|png',
    ])
    ->name('converter.show');
