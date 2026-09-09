<?php

namespace App\Listeners;

use App\Jobs\ExtractExifJob;
use Statamic\Events\AssetUploaded;

class ExtractExifOnUpload
{
    public function handle(AssetUploaded $event): void
    {
        $asset = $event->asset;

        if ($asset->container()->handle() !== 'photos') {
            return;
        }

        if ($asset->mimeType() !== 'image/jpeg') {
            return;
        }

        ExtractExifJob::dispatch($asset->id());
    }
}
