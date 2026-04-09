<?php

use App\Http\Controllers\CertificateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — DIU Student Services
|--------------------------------------------------------------------------
*/

// Public certificate verification — no auth required
Route::get('/verify/{ref}', [CertificateController::class, 'verify'])
    ->name('verify.certificate')
    ->middleware('throttle:30,1');

// Signed download route — validated by signature, auth optional
Route::get('/submissions/{ref}/download', [CertificateController::class, 'download'])
    ->name('submissions.download')
    ->middleware(['signed', 'auth:sanctum']);

// Generate a fresh download link (authenticated students only)
Route::post('/submissions/{ref}/download-link', [CertificateController::class, 'generateDownloadLink'])
    ->middleware('auth:sanctum');
