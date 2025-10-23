<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/unsplash/search', [App\Http\Controllers\UnsplashController::class, 'search']);
Route::get('/scorm/export', [App\Http\Controllers\ScormController::class, 'export']);