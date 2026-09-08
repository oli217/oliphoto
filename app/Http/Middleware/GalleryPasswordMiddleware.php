<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Symfony\Component\HttpFoundation\Response;

class GalleryPasswordMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        if (! preg_match('#^/galeries/([^/]+)$#', $request->getPathInfo(), $matches)) {
            return $next($request);
        }

        $slug = $matches[1];

        $entry = Entry::query()
            ->where('collection', 'galleries')
            ->where('slug', $slug)
            ->first();

        if (! $entry || ! $entry->get('password')) {
            return $next($request);
        }

        $expected = hash('sha256', $entry->get('password'));
        $stored   = session("gallery_access_{$entry->id()}");

        if ($stored === $expected) {
            return $next($request);
        }

        return response()->view('partials.password-form', [
            'entry' => $entry,
            'slug'  => $slug,
        ]);
    }
}
