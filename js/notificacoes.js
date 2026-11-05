
document.addEventListener("DOMContentLoaded", () => {
    const botao = document.getElementById("botaoNotificacoes");
    const painel = document.getElementById("painelNotificacoes");
    const fechar = document.getElementById("fecharNotificacoes");
    const contador = document.getElementById("contadorNotificacoes");
    const lista = document.getElementById("listaNotificacoes");
    const ativar = document.getElementById("ativarNotificacoesNavegador");

    if (!botao || !painel || !contador || !lista) return;

    let primeiraConsulta = true;
    let idsConhecidos = new Set();
    let consultando = false;

    function escapar(valor) {
        return String(valor ?? "").replace(/[&<>"']/g, caractere => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        })[caractere]);
    }

    function dataLegivel(valor) {
        if (!valor) return "Data não informada";

        const data = new Date(String(valor).replace(" ", "T"));

        if (Number.isNaN(data.getTime())) return valor;

        return data.toLocaleString("pt-BR");
    }

    function mostrarAviso(n) {
        if (!("Notification" in window)) return;
        if (Notification.permission !== "granted") return;

        const aviso = new Notification("Novo agendamento — SACM", {
            body: "Você recebeu um novo agendamento. Acesse o SACM para consultar os detalhes.",
            tag: "sacm-agendamento-" + n.id_agendamento,
            icon: "./img/SACM.png"
        });

        aviso.onclick = () => {
            window.focus();
            painel.hidden = false;
            botao.setAttribute("aria-expanded", "true");
            aviso.close();
        };
    }

    function renderizar(notificacoes) {
        if (!notificacoes.length) {
            lista.innerHTML =
                '<p class="estado-notificacoes">Nenhuma notificação ainda.</p>';
            return;
        }

        lista.innerHTML = notificacoes.map(n => {
            const lida = Number(n.lida) === 1;

            return `
                <article
                    class="item-notificacao ${lida ? "" : "nao-lida"}"
                    data-id="${Number(n.id_notificacao)}"
                    tabindex="0"
                    role="button"
                    aria-label="Marcar notificação como lida"
                >
                    <strong>Novo agendamento</strong>
                    <p>
                        ${n.assunto
                            ? "Assunto: " + escapar(n.assunto)
                            : "Um aluno realizou um novo agendamento."}
                    </p>
                    <small>
                        ${escapar(dataLegivel(n.criada_em))}
                    </small>
                </article>
            `;
        }).join("");
    }

    async function carregarNotificacoes() {
        if (consultando) return;
        consultando = true;

        try {
            const resposta = await fetch("./api/notificacoes.php", {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store",
                headers: { "Accept": "application/json" }
            });

            if (resposta.status === 401) {
                lista.innerHTML =
                    '<p class="estado-notificacoes">Entre no SACM para ver suas notificações.</p>';
                return;
            }

            if (!resposta.ok) {
                throw new Error("Falha ao consultar notificações.");
            }

            const dados = await resposta.json();

            if (!dados.sucesso) {
                throw new Error(dados.mensagem || "Erro na consulta.");
            }

            const notificacoes = dados.notificacoes || [];

            contador.textContent = dados.nao_lidas > 99
                ? "99+"
                : dados.nao_lidas;

            contador.hidden = Number(dados.nao_lidas) === 0;
            botao.setAttribute(
                "aria-label",
                `${dados.nao_lidas} notificações não lidas`
            );

            const idsAtuais = new Set(
                notificacoes.map(n => String(n.id_notificacao))
            );

            if (!primeiraConsulta) {
                notificacoes.forEach(n => {
                    const id = String(n.id_notificacao);

                    if (!idsConhecidos.has(id)) {
                        mostrarAviso(n);
                    }
                });
            }

            idsConhecidos = idsAtuais;
            primeiraConsulta = false;
            renderizar(notificacoes);

        } catch (erro) {
            console.error("Erro nas notificações SACM:", erro);
        } finally {
            consultando = false;
        }
    }

    async function marcarComoLida(id) {
        try {
            const resposta = await fetch(
                "./api/marcar-notificacao-lida.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({ id_notificacao: id })
                }
            );

            if (!resposta.ok) {
                throw new Error("Não foi possível atualizar.");
            }

            await carregarNotificacoes();
        } catch (erro) {
            console.error(erro);
            alert("Não foi possível marcar a notificação como lida.");
        }
    }

    botao.addEventListener("click", () => {
        painel.hidden = !painel.hidden;
        botao.setAttribute("aria-expanded", String(!painel.hidden));

        if (!painel.hidden) carregarNotificacoes();
    });

    fechar?.addEventListener("click", () => {
        painel.hidden = true;
        botao.setAttribute("aria-expanded", "false");
    });

    lista.addEventListener("click", evento => {
        const item = evento.target.closest(".item-notificacao");
        if (!item) return;

        marcarComoLida(Number(item.dataset.id));
    });

    lista.addEventListener("keydown", evento => {
        if (evento.key !== "Enter" && evento.key !== " ") return;

        const item = evento.target.closest(".item-notificacao");
        if (!item) return;

        evento.preventDefault();
        marcarComoLida(Number(item.dataset.id));
    });

    ativar?.addEventListener("click", async () => {
        if (!("Notification" in window)) {
            alert("Este navegador não oferece suporte a notificações.");
            return;
        }

        if (Notification.permission === "granted") {
            alert("Os avisos do navegador já estão ativados.");
            return;
        }

        if (Notification.permission === "denied") {
            alert(
                "As notificações foram bloqueadas no navegador. " +
                "Altere a permissão nas configurações do site."
            );
            return;
        }

        const permissao = await Notification.requestPermission();

        if (permissao === "granted") {
            alert("Avisos do navegador ativados!");
        } else {
            alert("Você não autorizou os avisos do navegador.");
        }
    });

    carregarNotificacoes();
    setInterval(carregarNotificacoes, 10000);
});
