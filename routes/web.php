<?php

use App\Http\Controllers\GalleryPasswordController;
use App\Http\Controllers\ZipDownloadController;
use Illuminate\Support\Facades\Route;

// Listing de toutes les galeries
Route::statamic('/galeries', 'galleries/index', ['title' => 'Galeries']);

// Déverrouillage d'une galerie protégée (Cap CAPTCHA via middleware cap.verify)
Route::post('/galeries/{slug}/unlock', [GalleryPasswordController::class, 'unlock'])
    ->middleware('cap.verify')
    ->name('gallery.unlock');

// Téléchargement ZIP d'une sélection de photos
Route::post('/api/zip', [ZipDownloadController::class, 'download'])
    ->name('gallery.zip');
