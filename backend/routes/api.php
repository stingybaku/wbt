<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\FrontendTextController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ModuleFlagsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\ProjectInvitesController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\ProjectSourceController;
use App\Http\Controllers\ScormController;
use App\Http\Controllers\PexelsController;
use App\Http\Controllers\UnsplashController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\ViewerController;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/webhooks/paypal', [PayPalWebhookController::class, 'handle']);
Route::get('/frontend-texts', [FrontendTextController::class, 'index']);

// Public viewer routes (token-gated, no auth required)
Route::get('/view/{token}',              [ViewerController::class, 'show']);
Route::post('/view/{token}/verify',      [ViewerController::class, 'verify']);
Route::post('/view/{token}/progress',    [ViewerController::class, 'updateProgress']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', ChangePasswordController::class);

    Route::get('/projects', [ProjectsController::class, 'index']);
    Route::post('/projects', [ProjectsController::class, 'store']);
    Route::get('/projects/{id}', [ProjectsController::class, 'show']);
    Route::post('/projects/{id}/source', [ProjectSourceController::class, 'store']);

    Route::post('/projects/{id}/module/generate', [ModuleController::class, 'generate'])->middleware('quota:module');
    Route::get('/projects/{id}/module', [ModuleController::class, 'show']);
    Route::put('/projects/{id}/module', [ModuleController::class, 'update']);
    Route::post('/projects/{id}/module/flags', [ModuleFlagsController::class, 'store']);

    Route::get('/unsplash/search', [UnsplashController::class, 'search']);
    Route::get('/pexels/search',   [PexelsController::class,  'search']);

    Route::post('/projects/{id}/scorm', [ScormController::class, 'export']);

    Route::post('/projects/{id}/events', [EventsController::class, 'store']);

    Route::get('/projects/{id}/report', [ReportController::class, 'show']);

    // Invites
    Route::get('/projects/{id}/invites',                                    [ProjectInvitesController::class, 'index']);
    Route::post('/projects/{id}/invites',                                   [ProjectInvitesController::class, 'store'])->middleware('quota:seat');
    Route::delete('/projects/{id}/invites/{inviteId}',                      [ProjectInvitesController::class, 'revoke']);
    Route::get('/projects/{id}/invites/{inviteId}/certificate',             [ProjectInvitesController::class, 'certificate']);

    // Billing
    Route::get('/billing/plans',             [BillingController::class, 'plans']);
    Route::get('/billing/current',           [BillingController::class, 'current']);
    Route::post('/billing/checkout',         [BillingController::class, 'checkout']);
    Route::post('/billing/capture-order',    [BillingController::class, 'captureOrder']);
    Route::post('/billing/cancel',           [BillingController::class, 'cancel']);
    Route::get('/billing/transactions',      [BillingController::class, 'transactions']);
    Route::get('/billing/transactions/{id}/invoice', [BillingController::class, 'invoice']);

    // Notifications
    Route::get('/notifications',                    [NotificationsController::class, 'index']);
    Route::post('/notifications/read-all',          [NotificationsController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read',         [NotificationsController::class, 'markRead']);
});
