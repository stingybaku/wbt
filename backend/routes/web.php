<?php

use Illuminate\Support\Facades\Route;

// Route::view rather than a closure so the route table stays serializable
// for `php artisan route:cache`.
Route::view('/', 'welcome');

// NOTE: /unsplash/search and /scorm/export previously lived here, outside any
// auth middleware. Both are already registered under /api behind auth:sanctum,
// and the frontend only ever calls the /api versions. They were removed because
// unauthenticated callers could proxy the Unsplash API key and drive the SCORM
// export's file and zip writes.
