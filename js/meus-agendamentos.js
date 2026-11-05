
"use strict";

/* =====================================================
   SACM — MEUS AGENDAMENTOS
   Carregamento, filtros, contadores e cancelamento
===================================================== */

const $ = (id) => document.getElementById(id);

const lista = $("listaAgendamentos");
const mensagem = $("mensagem");
const filtro = $("filtroStatus");

let agendamentos = [];
let cancelando = false;

/* =====================================================
   NORMALIZAR STATUS
===================================================== */

function normalizarStatus(status) {
    const valor = String(status || "agendado")
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");

    if (valor.includes("cancel")) return "cancelado";

    if (
        valor.includes("realiz") ||
        valor.includes("conclu")
    ) {
        return "realizado";
    }

    return "agendado";
}

/* =====================================================
   ESCAPAR TEXTO
===================================================== */

function escapar(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, caractere => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[caractere]);
}

/* =====================================================
   FORMATAR DATA
===================================================== */

function formatarData(data) {
    if (!data) return "Não informada";

    const valor = String(data).slice(0, 10);
    const partes = valor.split("-");

    if (partes.length === 3 && partes[0].length === 4) {
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    return valor;
}

/* =====================================================
   ATUALIZAR CONTADORES
===================================================== */

function atualizarResumo() {
    const total = agendamentos.length;

    const agendados = agendamentos.filter(item =>
        normalizarStatus(item.status) === "agendado"
    ).length;

    const realizados = agendamentos.filter(item =>
        normalizarStatus(item.status) === "realizado"
    ).length;

    if ($("totalAgendamentos")) {
        $("totalAgendamentos").textContent = total;
    }

    if ($("totalAgendados")) {
        $("totalAgendados").textContent = agendados;
    }

    if ($("totalRealizados")) {
        $("totalRealizados").textContent = realizados;
    }
}

/* =====================================================
   MENSAGENS
===================================================== */

function mostrarMensagem(texto) {
    if (!mensagem) return;

    mensagem.hidden = false;
    mensagem.textContent = texto;
}

/* =====================================================
   RENDERIZAR LISTA
===================================================== */

function renderizar() {
    if (!lista) return;

    const selecionado = filtro ? filtro.value : "todos";

    const filtrados = agendamentos.filter(item =>
        selecionado === "todos" ||
        normalizarStatus(item.status) === selecionado
    );

    lista.replaceChildren();

    if (filtrados.length === 0) {
        mostrarMensagem(
            agendamentos.length > 0
                ? "Nenhum agendamento encontrado para esse filtro."
                : "Você ainda não possui agendamentos."
        );

        return;
    }

    if (mensagem) mensagem.hidden = true;

    filtrados.forEach(item => {
        const status = normalizarStatus(item.status);

        const cartao = document.createElement("article");
        cartao.className = "agendamento";

        const nomeDisciplina =
            item.disciplina || "Monitoria";

        const nomeMonitor =
            item.monitor || "Não informado";

        const data = formatarData(item.data_agendamento);

        const horario =
            String(item.hora_inicio || "Não informado").slice(0, 5);

        const assunto = item.assunto || "Não informado";
        const observacao = item.observacao || "Nenhuma";

        const statusFormatado =
            status.charAt(0).toUpperCase() + status.slice(1);

        cartao.innerHTML = `
            <div class="agendamento-cabecalho">
                <div>
                    <h3>${escapar(nomeDisciplina)}</h3>
                    <p class="monitor">
                        Monitor: ${escapar(nomeMonitor)}
                    </p>
                </div>

                <span class="status ${status}">
                    ${escapar(statusFormatado)}
                </span>
            </div>

            <div class="detalhes">
                <div class="detalhe">
                    <span>Data</span>
                    <strong>${escapar(data)}</strong>
                </div>

                <div class="detalhe">
                    <span>Horário</span>
                    <strong>${escapar(horario)}</strong>
                </div>

                <div class="detalhe">
                    <span>Assunto</span>
                    <strong>${escapar(assunto)}</strong>
                </div>

                <div class="detalhe">
                    <span>Observação</span>
                    <strong>${escapar(observacao)}</strong>
                </div>
            </div>
        `;

        /* Só mostra o botão para agendamentos ativos. */
        if (
            status === "agendado" &&
            item.id_agendamento
        ) {
            const areaBotoes = document.createElement("div");
            areaBotoes.className = "acoes-agendamento";

            const botaoCancelar = document.createElement("button");
            botaoCancelar.type = "button";
            botaoCancelar.className = "botao-cancelar";
            botaoCancelar.textContent = "Cancelar agendamento";
            botaoCancelar.setAttribute(
                "aria-label",
                `Cancelar agendamento de ${nomeDisciplina}`
            );

            botaoCancelar.addEventListener("click", () => {
                cancelarAgendamento(
                    item.id_agendamento,
                    botaoCancelar
                );
            });

            areaBotoes.appendChild(botaoCancelar);
            cartao.appendChild(areaBotoes);
        }

        lista.appendChild(cartao);
    });
}

/* =====================================================
   CANCELAR AGENDAMENTO
===================================================== */

async function cancelarAgendamento(idAgendamento, botao) {
    if (cancelando) return;

    const confirmado = window.confirm(
        "Deseja realmente cancelar este agendamento?\n\n" +
        "Essa ação não poderá ser desfeita por esta tela."
    );

    if (!confirmado) return;

    const matricula = localStorage.getItem("matricula");

    if (!matricula) {
        alert("Sua sessão não foi identificada. Entre novamente no SACM.");
        return;
    }

    cancelando = true;

    const textoOriginal = botao.textContent;

    botao.disabled = true;
    botao.textContent = "Cancelando...";

    try {
        const dadosEnvio = new URLSearchParams();

        dadosEnvio.set("id_agendamento", String(idAgendamento));
        dadosEnvio.set("matricula", matricula);

        const resposta = await fetch(
            "./api/cancelar_agendamento.php",
            {
                method: "POST",
                cache: "no-store",
                headers: {
                    "Accept": "application/json",
                    "Content-Type":
                        "application/x-www-form-urlencoded;charset=UTF-8"
                },
                body: dadosEnvio.toString()
            }
        );

        const textoResposta = await resposta.text();

        let dados;

        try {
            dados = JSON.parse(textoResposta);
        } catch {
            console.error(
                "SACM: resposta inválida ao cancelar.",
                textoResposta
            );

            throw new Error(
                "O servidor retornou uma resposta inválida."
            );
        }

        if (!resposta.ok || !dados.sucesso) {
            throw new Error(
                dados.mensagem || "Não foi possível cancelar o agendamento."
            );
        }

        alert(dados.mensagem || "Agendamento cancelado com sucesso.");

        /* Busca o estado atualizado diretamente no banco. */
        await carregarAgendamentos();

    } catch (erro) {
        console.error("SACM — Erro ao cancelar:", erro);

        alert(
            erro.message ||
            "Não foi possível cancelar. Tente novamente."
        );

        botao.disabled = false;
        botao.textContent = textoOriginal;

    } finally {
        cancelando = false;
    }
}

/* =====================================================
   CARREGAR AGENDAMENTOS
===================================================== */

async function carregarAgendamentos() {
    if (!lista || !mensagem) return;

    const matricula = localStorage.getItem("matricula");

    if (!matricula) {
        agendamentos = [];
        atualizarResumo();
        renderizar();

        mostrarMensagem(
            "Não foi possível identificar seu login. Entre novamente no SACM."
        );

        return;
    }

    try {
        mostrarMensagem("Carregando seus agendamentos...");

        const resposta = await fetch(
            "./api/meus_agendamentos.php?matricula=" +
            encodeURIComponent(matricula),
            {
                method: "GET",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            }
        );

        const textoResposta = await resposta.text();

        let dados;

        try {
            dados = JSON.parse(textoResposta);
        } catch {
            console.error(
                "SACM: resposta inválida ao carregar agendamentos.",
                textoResposta
            );

            throw new Error(
                "A resposta do servidor é inválida. Verifique o PHP."
            );
        }

        if (!resposta.ok || !dados.sucesso) {
            throw new Error(
                dados.mensagem ||
                `Erro ao carregar agendamentos (HTTP ${resposta.status}).`
            );
        }

        agendamentos = Array.isArray(dados.agendamentos)
            ? dados.agendamentos
            : [];

        atualizarResumo();
        renderizar();

    } catch (erro) {
        console.error("SACM — Erro ao carregar:", erro);

        agendamentos = [];
        atualizarResumo();
        lista.replaceChildren();

        mostrarMensagem(
            erro.message ||
            "Não foi possível carregar seus agendamentos."
        );
    }
}

/* =====================================================
   FILTRO
===================================================== */

if (filtro) {
    filtro.addEventListener("change", renderizar);
}

/* =====================================================
   MENU LATERAL
===================================================== */

const menu = $("menu");
const fundoMenu = $("fundoMenu");

function abrirMenu() {
    if (menu) menu.classList.add("aberto");
    if (fundoMenu) fundoMenu.classList.add("visivel");

    document.body.classList.add("menu-aberto");
}

function fecharMenu() {
    if (menu) menu.classList.remove("aberto");
    if (fundoMenu) fundoMenu.classList.remove("visivel");

    document.body.classList.remove("menu-aberto");
}

if (fundoMenu) {
    fundoMenu.addEventListener("click", fecharMenu);
}

document.addEventListener("keydown", evento => {
    if (evento.key === "Escape") fecharMenu();
});

/* =====================================================
   INICIALIZAÇÃO
===================================================== */

document.addEventListener("DOMContentLoaded", () => {
    atualizarResumo();
    carregarAgendamentos();
});
