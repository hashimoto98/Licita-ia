/**
 * LicitAI — Service Worker (PWA)
 * Estratégia: Network First com fallback para cache offline.
 * Garante que o usuário sempre receba dados frescos quando online.
 */

const CACHE_NAME = 'licita-ai-v1';
const STATIC_ASSETS = [
    '/licita-ia/',
    '/licita-ia/?page=login',
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
];

// ── Instalação: pré-carrega assets estáticos ───────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache =>
            cache.addAll(STATIC_ASSETS).catch(() => {})
        )
    );
    self.skipWaiting();
});

// ── Ativação: limpa caches antigos ────────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// ── Fetch: Network First ──────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    // Não intercepta requisições POST/não-GET
    if (event.request.method !== 'GET') return;

    // Não intercepta APIs externas
    const url = new URL(event.request.url);
    if (url.origin !== location.origin) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Atualiza o cache com a resposta fresca
                if (response && response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache =>
                        cache.put(event.request, responseClone)
                    );
                }
                return response;
            })
            .catch(() =>
                // Sem rede: serve do cache
                caches.match(event.request).then(cached => {
                    if (cached) return cached;
                    // Fallback offline genérico
                    return new Response(
                        '<html><body style="font-family:sans-serif;text-align:center;padding:2rem">' +
                        '<h2>LicitAI — Sem conexão</h2>' +
                        '<p>Verifique sua conexão com a internet e recarregue.</p></body></html>',
                        { headers: { 'Content-Type': 'text/html' } }
                    );
                })
            )
    );
});
