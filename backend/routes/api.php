<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\FrontendTextController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ModuleFlagsController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\ProjectSourceController;
use App\Http\Controllers\ScormController;
use App\Http\Controllers\UnsplashController;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/frontend-texts', [FrontendTextController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/projects', [ProjectsController::class, 'index']);
    Route::post('/projects', [ProjectsController::class, 'store']);
    Route::get('/projects/{id}', [ProjectsController::class, 'show']);
    Route::post('/projects/{id}/source', [ProjectSourceController::class, 'store']);

    Route::post('/projects/{id}/module/generate', [ModuleController::class, 'generate']);
    Route::get('/projects/{id}/module', [ModuleController::class, 'show']);
    Route::put('/projects/{id}/module', [ModuleController::class, 'update']);
    Route::post('/projects/{id}/module/flags', [ModuleFlagsController::class, 'store']);

    Route::get('/unsplash/search', [UnsplashController::class, 'search']);

    Route::post('/projects/{id}/scorm', [ScormController::class, 'export']);

    Route::post('/projects/{id}/events', [EventsController::class, 'store']);
});
