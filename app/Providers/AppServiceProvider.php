<?php

namespace App\Providers;

use App\Listeners\ExtractExifOnUpload;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Statamic\Events\AssetUploaded;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(AssetUploaded::class, ExtractExifOnUpload::class);
    }
}
