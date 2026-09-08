<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès protégé — {{ $entry->get('title') }}</title>
    @vite(['resources/css/site.css'])
</head>
<body class="h-full bg-zinc-950 text-zinc-100 flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        {{-- Logo / lien retour --}}
        <div class="mb-8 text-center">
            <a href="/galeries" class="text-zinc-500 hover:text-zinc-300 text-sm transition-colors">
                ← Toutes les galeries
            </a>
        </div>

        {{-- Carte --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-8 shadow-2xl">

            <div class="mb-6 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-zinc-800 mb-4">
                    <svg class="w-5 h-5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <h1 class="text-lg font-semibold text-zinc-100">{{ $entry->get('title') }}</h1>
                <p class="text-sm text-zinc-400 mt-1">Cette galerie est protégée par un mot de passe.</p>
            </div>

            {{-- Erreurs --}}
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-900/30 border border-red-800 rounded-lg text-sm text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                id="password-form"
                method="POST"
                action="{{ route('gallery.unlock', $slug) }}"
            >
                @csrf

                {{-- Cap iframe (proof-of-work isolé, CSP permissive via /cap-frame) --}}
                @capFrame($csp_nonce, 'cap-frame')

                <div class="mb-4">
                    <label for="password" class="block text-xs font-medium text-zinc-400 mb-1.5">
                        Mot de passe
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-zinc-100 text-sm placeholder-zinc-500 focus:outline-none focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500"
                        placeholder="••••••••"
                    >
                </div>

                <button
                    type="submit"
                    id="submit-btn"
                    class="w-full py-2.5 bg-zinc-100 hover:bg-white text-zinc-900 text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Accéder à la galerie
                </button>

                <p id="cap-status" class="text-xs text-zinc-500 text-center mt-3 hidden">
                    Vérification de sécurité en cours…
                </p>
            </form>
        </div>
    </div>

    {{-- Logique Cap : auto-démarrage + validation avant soumission --}}
    <script nonce="{{ $csp_nonce }}">
    (function () {
        var form   = document.getElementById('password-form');
        var btn    = document.getElementById('submit-btn');
        var status = document.getElementById('cap-status');
        var iframe = document.getElementById('cap-frame');
        var token  = document.getElementById('cap-frame-token');

        // Démarre le proof-of-work dès que l'iframe est chargée
        // Quand id='cap-frame' (défaut @capFrame), la fn est window.capSolve
        if (iframe) {
            iframe.addEventListener('load', function () {
                window.capSolve();
            });
        }

        // Avant soumission : s'assure que le token Cap est prêt
        form.addEventListener('submit', function (e) {
            if (token && token.value) {
                return; // Token prêt, on laisse partir
            }

            e.preventDefault();
            btn.disabled = true;
            status.classList.remove('hidden');

            // Relance le proof-of-work si nécessaire
            if (typeof window.capSolve === 'function') {
                window.capSolve();
            }

            // Poll jusqu'à obtention du token (max 15s)
            var waited = 0;
            var poll = setInterval(function () {
                waited += 200;
                if (token && token.value) {
                    clearInterval(poll);
                    form.submit();
                } else if (waited >= 15000) {
                    clearInterval(poll);
                    btn.disabled = false;
                    status.textContent = 'Vérification échouée. Rechargez la page.';
                }
            }, 200);
        });
    })();
    </script>

</body>
</html>
