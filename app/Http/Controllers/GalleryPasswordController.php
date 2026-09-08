<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;

class GalleryPasswordController extends Controller
{
    public function unlock(Request $request, string $slug): RedirectResponse
    {
        $entry = Entry::query()
            ->where('collection', 'galleries')
            ->where('slug', $slug)
            ->first();

        if (! $entry || ! $entry->get('password')) {
            return redirect("/galeries/{$slug}");
        }

        $request->validate([
            'password'  => 'required|string',
            'cap-token' => 'required|string',
        ]);

        if (! hash_equals(
            hash('sha256', $entry->get('password')),
            hash('sha256', $request->input('password'))
        )) {
            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }

        session()->put(
            "gallery_access_{$entry->id()}",
            hash('sha256', $entry->get('password'))
        );

        return redirect("/galeries/{$slug}");
    }
}
