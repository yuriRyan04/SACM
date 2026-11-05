
/* ==========================================================
   SACM — CRÉDITOS DA EQUIPE
   Arquivo: js/creditos.js
========================================================== */

document.addEventListener("DOMContentLoaded", function () {

    document.body.classList.add("creditos-carregados");

    /*
       FOTOS DOS INTEGRANTES

       Se uma foto não existir ou não carregar, será exibido
       um círculo com as iniciais indicadas no atributo alt.
    */

    const fotos = document.querySelectorAll(".foto-integrante");

    fotos.forEach(function (foto) {

        function mostrarFallback() {

            if (foto.dataset.fallback === "sim") {
                return;
            }

            foto.dataset.fallback = "sim";

            const nomeAlternativo = foto.getAttribute("alt") || "Integrante";
            const numero = nomeAlternativo.match(/\d+/);
            const iniciais = numero ? "P" + numero[0] : "SACM";

            const substituto = document.createElement("div");

            substituto.className = "foto-fallback";
            substituto.textContent = iniciais;
            substituto.setAttribute("role", "img");
            substituto.setAttribute("aria-label", nomeAlternativo);

            foto.replaceWith(substituto);
        }

        foto.addEventListener("error", mostrarFallback);

        /*
           Verifica imagens que já falharam antes de o
           evento error ser registrado.
        */

        if (foto.complete && foto.naturalWidth === 0) {
            mostrarFallback();
        }

    });

    /*
       ANIMAÇÃO AO ENTRAR NA TELA

       Os cartões aparecem suavemente quando ficam visíveis.
       Em navegadores sem IntersectionObserver, os cartões
       permanecem visíveis normalmente.
    */

    const cartoes = document.querySelectorAll(".cartao-integrante");

    if ("IntersectionObserver" in window) {

        const observador = new IntersectionObserver(
            function (entradas, observer) {

                entradas.forEach(function (entrada) {

                    if (entrada.isIntersecting) {

                        entrada.target.classList.add("cartao-visivel");

                        observer.unobserve(entrada.target);

                    }

                });

            },
            {
                threshold: 0.08
            }
        );

        cartoes.forEach(function (cartao) {
            observador.observe(cartao);
        });

    } else {

        cartoes.forEach(function (cartao) {
            cartao.classList.add("cartao-visivel");
        });

    }

});
