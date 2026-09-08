<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ZipDownloadController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'gallery'  => 'required|string',
            'assets'   => 'required|array|min:1|max:200',
            'assets.*' => 'required|string',
        ]);

        // Vérifie l'accès si la galerie est protégée
        $entry = Entry::query()
            ->where('collection', 'galleries')
            ->where('slug', $validated['gallery'])
            ->first();

        if ($entry && $entry->get('password')) {
            $expected = hash('sha256', $entry->get('password'));
            $stored   = session("gallery_access_{$entry->id()}");
            abort_if($stored !== $expected, 403, 'Accès refusé.');
        }

        $assets = collect($validated['assets'])
            ->map(fn (string $id) => Asset::find($id))
            ->filter(fn ($asset) => $asset && $asset->container()->handle() === 'photos')
            ->values();

        abort_if($assets->isEmpty(), 422, 'Aucune photo valide sélectionnée.');

        $tmpFile = tempnam(sys_get_temp_dir(), 'oliphoto_') . '.zip';

        $zip = new ZipArchive();
        abort_if($zip->open($tmpFile, ZipArchive::CREATE) !== true, 500, 'Impossible de créer l\'archive.');

        foreach ($assets as $asset) {
            $path = $asset->resolvedPath();
            if (is_file($path)) {
                // Préfixe par ID court pour éviter les doublons de noms de fichiers
                $zip->addFile($path, $asset->basename());
            }
        }

        $zip->close();

        return response()
            ->download($tmpFile, 'selection-photos.zip')
            ->deleteFileAfterSend();
    }
}
