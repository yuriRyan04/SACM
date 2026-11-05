
/* ==========================================================
   SACM - REGISTRO DO PWA
========================================================== */

(() => {
    "use strict";

    let eventoInstalacao = null;

    function iniciarPWA() {
        /* Registrar o Service Worker */
        if ("serviceWorker" in navigator && window.isSecureContext) {
            window.addEventListener("load", async () => {
                try {
                    await navigator.serviceWorker.register("./sw.js", {
                        scope: "./"
                    });

                    console.info("SACM: Service Worker registrado.");
                } catch (erro) {
                    console.error(
                        "SACM: falha ao registrar Service Worker.",
                        erro
                    );
                }
            });
        } else {
            console.warn(
                "SACM: esta origem precisa ser segura para habilitar o PWA."
            );
        }

        criarBotaoInstalacao();

        /* Evento de instalação disponível em alguns navegadores */
        window.addEventListener("beforeinstallprompt", (evento) => {
            evento.preventDefault();
            eventoInstalacao = evento;
            atualizarBotao();
        });

        window.addEventListener("appinstalled", () => {
            eventoInstalacao = null;
            atualizarBotao();
            console.info("SACM instalado.");
        });
    }

    function criarBotaoInstalacao() {
        if (document.getElementById("instalarSACM")) {
            return;
        }

        const botao = document.createElement("button");

        botao.id = "instalarSACM";
        botao.type = "button";
        botao.textContent = "Instalar SACM";
        botao.hidden = true;

        botao.style.cssText = [
            "position:fixed",
            "right:18px",
            "bottom:18px",
            "z-index:9999",
            "padding:13px 18px",
            "border:0",
            "border-radius:10px",
            "background:#900E01",
            "color:#fff",
            "font-weight:bold",
            "cursor:pointer",
            "box-shadow:0 4px 14px rgba(0,0,0,.25)"
        ].join(";");

        botao.addEventListener("click", instalarSACM);

        document.body.appendChild(botao);
    }

    function atualizarBotao() {
        const botao = document.getElementById("instalarSACM");

        if (botao) {
            botao.hidden = !eventoInstalacao;
        }
    }

    async function instalarSACM() {
        if (!eventoInstalacao) {
            return;
        }

        eventoInstalacao.prompt();

        try {
            await eventoInstalacao.userChoice;
        } catch (erro) {
            console.warn("A instalação não foi concluída.", erro);
        }

        eventoInstalacao = null;
        atualizarBotao();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", iniciarPWA);
    } else {
        iniciarPWA();
    }
})();
