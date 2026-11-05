
/* ==========================================================
   SACM - SERVICE WORKER
   Versão inicial com cache restrito e seguro
========================================================== */

const CACHE_NAME = "sacm-estatico-v1";

const ARQUIVOS_PUBLICOS = [
    "./offline.html",
    "./img/icons/icon-192.png",
    "./img/icons/icon-512.png",
    "./img/icons/apple-touch-icon.png"
];

/* Instalação do Service Worker */
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(ARQUIVOS_PUBLICOS))
            .then(() => self.skipWaiting())
    );
});

/* Ativação e remoção de caches antigos do SACM */
self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys()
            .then((nomes) => Promise.all(
                nomes
                    .filter((nome) =>
                        nome.startsWith("sacm-estatico-") &&
                        nome !== CACHE_NAME
                    )
                    .map((nome) => caches.delete(nome))
            ))
            .then(() => self.clients.claim())
    );
});

/* Interceptação das requisições */
self.addEventListener("fetch", (event) => {
    const requisicao = event.request;
    const url = new URL(requisicao.url);

    /* Não interceptar outros domínios */
    if (url.origin !== self.location.origin) {
        return;
    }

    /* Não interceptar envios de formulários ou outros métodos */
    if (requisicao.method !== "GET") {
        return;
    }

    const caminho = url.pathname.toLowerCase();

    /* APIs e páginas PHP sempre consultam o servidor */
    const ehDinamico =
        caminho.endsWith(".php") ||
        caminho.includes("/api/") ||
        caminho.includes("/login") ||
        caminho.includes("/logout");

    if (ehDinamico) {
        event.respondWith(
            fetch(requisicao, { cache: "no-store" })
                .catch(() => {
                    if (requisicao.mode === "navigate") {
                        return caches.match("./offline.html");
                    }

                    return new Response(
                        JSON.stringify({
                            sucesso: false,
                            mensagem: "Servidor indisponível. Tente novamente."
                        }),
                        {
                            status: 503,
                            headers: {
                                "Content-Type":
                                    "application/json; charset=utf-8",
                                "Cache-Control": "no-store"
                            }
                        }
                    );
                })
        );

        return;
    }

    /* Páginas HTML não são guardadas no cache */
    if (requisicao.mode === "navigate") {
        event.respondWith(
            fetch(requisicao, { cache: "no-store" })
                .catch(() => caches.match("./offline.html"))
        );

        return;
    }

    /* Somente arquivos públicos explicitamente permitidos */
    const permitidos = ARQUIVOS_PUBLICOS.map(
        (arquivo) => new URL(arquivo, self.registration.scope).href
    );

    if (!permitidos.includes(url.href)) {
        return;
    }

    event.respondWith(
        caches.match(requisicao).then((resposta) => {
            if (resposta) {
                return resposta;
            }

            return fetch(requisicao);
        })
    );
});
