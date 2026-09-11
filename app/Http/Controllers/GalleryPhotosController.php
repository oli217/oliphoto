<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Statamic\Facades\Image;

class GalleryPhotosController extends Controller
{
    const PER_PAGE = 50;

    public function index(Request $request, string $slug): JsonResponse
    {
        $entry = Entry::query()
            ->where('collection', 'galleries')
            ->where('slug', $slug)
            ->first();

        abort_if(! $entry, 404);

        if ($entry->get('password')) {
            $expected = hash('sha256', $entry->get('password'));
            $stored   = session("gallery_access_{$entry->id()}");
            abort_if($stored !== $expected, 403);
        }

        // OrderedQueryBuilder applique take() avant skip() → skip() après take(50) donne vide.
        // On charge toute la collection ordonnée, puis on slice côté PHP.
        $allPhotos = $entry->augmentedValue('photos')->value()->get();
        $total     = $allPhotos->count();
        $page      = max(1, (int) $request->query('page', 1));
        $offset    = ($page - 1) * self::PER_PAGE;
        $slice     = $allPhotos->slice($offset, self::PER_PAGE)->values();

        $photos = $slice->map(fn ($asset) => [
            'id'            => $asset->id(),
            'url'           => $asset->url(),
            'width'         => $asset->width(),
            'height'        => $asset->height(),
            'webp_url'      => Image::manipulate($asset, ['w' => 900, 'q' => 82, 'fm' => 'webp']),
            'exif_camera'   => $asset->get('exif_camera'),
            'exif_lens'     => $asset->get('exif_lens'),
            'exif_focal'    => $asset->get('exif_focal_length'),
            'exif_aperture' => $asset->get('exif_aperture'),
            'exif_shutter'  => $asset->get('exif_shutter_speed'),
            'exif_iso'      => $asset->get('exif_iso'),
            'exif_date'     => $asset->get('exif_date_taken'),
        ]);

        return response()->json([
            'photos'       => $photos,
            'current_page' => $page,
            'has_more'     => ($offset + self::PER_PAGE) < $total,
            'total'        => $total,
        ]);
    }
}
