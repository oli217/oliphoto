<?php

use App\Http\Controllers\GalleryPasswordController;
use App\Http\Controllers\ZipDownloadController;
use Illuminate\Support\Facades\Route;

// Webcron — déclenche le scheduler et vide la queue (protégé par token)
Route::get('/webcron/run', function () {
    $token = request('token');
    if (!$token || $token !== config('app.scheduler_token')) {
        abort(403);
    }
    Artisan::call('schedule:run');
    Artisan::call('queue:work --stop-when-empty --max-time=50');
    return response('OK ' . now()->toDateTimeString(), 200)
        ->header('Content-Type', 'text/plain');
})->middleware('throttle:10,1')->name('webcron.run');


// Listing de toutes les galeries
Route::statamic('/galeries', 'galleries/index', ['title' => 'Galeries']);

// Déverrouillage d'une galerie protégée (Cap CAPTCHA via middleware cap.verify)
Route::post('/galeries/{slug}/unlock', [GalleryPasswordController::class, 'unlock'])
    ->middleware('cap.verify')
    ->name('gallery.unlock');

// Téléchargement ZIP d'une sélection de photos
Route::post('/api/zip', [ZipDownloadController::class, 'download'])
    ->name('gallery.zip');
