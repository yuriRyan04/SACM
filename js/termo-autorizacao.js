/* ==========================================================
   SACM — TERMO DE AUTORIZAÇÃO
   Habilitação do botão, registro do aceite e redirecionamento
========================================================== */

"use strict";

// ==========================================================
// 1. CONFIGURAÇÃO DAS APIs
// ==========================================================

const API_VERIFICAR_TERMO = "./api/verificar_termo.php";
const API_TOKEN_TERMO = "./api/token_termo.php";
const API_ACEITAR_TERMO = "./api/aceitar_termo.php";

// ==========================================================
// 2. ELEMENTOS DA PÁGINA
// ==========================================================

function obterCheckbox() {
    return document.getElementById("aceiteTermo") ||
           document.getElementById("aceitoTermo") ||
           document.getElementById("aceite");
}

function obterBotao() {
    return document.getElementById("btnAceitar") ||
           document.getElementById("btnAceitarTermo");
}

function obterMensagem() {
    return document.getElementById("mensagemEstado");
}

// ==========================================================
// 3. MENSAGENS NA TELA
// ==========================================================

function mostrarMensagem(texto, tipo = "") {
    const mensagem = obterMensagem();

    if (!mensagem) {
        return;
    }

    mensagem.textContent = texto;
    mensagem.className = "informacao-final";

    if (tipo === "erro") {
        mensagem.classList.add("mensagem", "erro");
    } else if (tipo === "sucesso") {
        mensagem.classList.add("mensagem", "sucesso");
    } else if (tipo === "aviso") {
        mensagem.classList.add("mensagem", "aviso");
    }
}

// ==========================================================
// 4. NORMALIZAR PERFIL
// ==========================================================

function normalizarPerfil(tipo) {
    const perfil = String(tipo || "")
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");

    if (perfil === "aluno") {
        return "aluno";
    }

    if (perfil === "professor") {
        return "professor";
    }

    if (
        perfil === "gestao" ||
        perfil === "gestor" ||
        perfil === "administrador"
    ) {
        return "gestao";
    }

    return "";
}

// ==========================================================
// 5. DESTINO DE CADA PERFIL
// ==========================================================

function obterPaginaDestino(tipo) {
    const perfil = normalizarPerfil(tipo);

    const paginas = {
        aluno: "./Inicio.html",
        professor: "./Inicio-professor.html",
        gestao: "./Inicio-gestao.html"
    };

    return paginas[perfil] || null;
}

function redirecionarUsuario(tipoRecebido = null) {
    const tipo = normalizarPerfil(
        tipoRecebido || localStorage.getItem("tipo")
    );

    const destino = obterPaginaDestino(tipo);

    if (!destino) {
        console.error("Perfil não identificado:", tipoRecebido);

        mostrarMensagem(
            "Não foi possível identificar seu perfil. Faça login novamente.",
            "erro"
        );

        return;
    }

    localStorage.setItem("tipo", tipo);
    window.location.replace(destino);
}

// ==========================================================
// 6. REQUISIÇÕES JSON
// ==========================================================

async function requisitarJSON(url, opcoes = {}) {
    const resposta = await fetch(url, {
        credentials: "same-origin",
        cache: "no-store",
        ...opcoes
    });

    const texto = await resposta.text();

    let dados;

    try {
        dados = JSON.parse(texto);
    } catch (erro) {
        console.error("Resposta não é JSON:", url, texto);

        throw new Error(
            "O servidor retornou uma resposta inválida. Verifique o PHP."
        );
    }

    if (!resposta.ok) {
        const erro = new Error(
            dados.erro ||
            dados.mensagem ||
            `Erro HTTP ${resposta.status}.`
        );

        erro.status = resposta.status;
        erro.dados = dados;

        throw erro;
    }

    return dados;
}

// ==========================================================
// 7. HABILITAR OU DESABILITAR O BOTÃO
// ==========================================================

function atualizarBotao() {
    const checkbox = obterCheckbox();
    const botao = obterBotao();

    if (!checkbox || !botao) {
        console.error(
            "Não foi encontrado o checkbox ou o botão do termo."
        );
        return;
    }

    botao.disabled = !checkbox.checked;

    if (checkbox.checked) {
        mostrarMensagem(
            "Confirmação marcada. Você já pode aceitar o termo."
        );
    } else {
        mostrarMensagem(
            "Marque a caixa acima para habilitar o botão."
        );
    }
}

// ==========================================================
// 8. VERIFICAR SE O TERMO JÁ FOI ACEITO
// ==========================================================

async function verificarEstadoTermo() {
    try {
        const dados = await requisitarJSON(
            API_VERIFICAR_TERMO
        );

        if (dados.sucesso !== true) {
            console.error("Falha ao verificar o termo:", dados);
            return;
        }

        if (dados.tipo) {
            const perfil = normalizarPerfil(dados.tipo);

            if (perfil) {
                localStorage.setItem("tipo", perfil);
            }
        }

        if (dados.aceito === true) {
            const perfil =
                dados.tipo || localStorage.getItem("tipo");

            redirecionarUsuario(perfil);
        }
    } catch (erro) {
        console.error("Erro ao verificar o termo:", erro);

        mostrarMensagem(
            "Não foi possível verificar o aceite agora. Você pode tentar novamente.",
            "erro"
        );
    }
}

// ==========================================================
// 9. OBTER TOKEN DE SEGURANÇA CSRF
// ==========================================================

async function obterTokenTermo() {
    const dados = await requisitarJSON(
        API_TOKEN_TERMO
    );

    if (dados.sucesso !== true || !dados.token) {
        throw new Error(
            dados.erro ||
            dados.mensagem ||
            "Não foi possível obter o token de segurança."
        );
    }

    return dados.token;
}

// ==========================================================
// 10. REGISTRAR O ACEITE
// ==========================================================

async function aceitarTermo() {
    const checkbox = obterCheckbox();
    const botao = obterBotao();

    if (!checkbox || !botao) {
        mostrarMensagem(
            "Não foi possível localizar os elementos do termo.",
            "erro"
        );
        return;
    }

    if (!checkbox.checked) {
        botao.disabled = true;

        mostrarMensagem(
            "Marque a caixa de confirmação antes de continuar.",
            "aviso"
        );
        return;
    }

    if (botao.dataset.enviando === "true") {
        return;
    }

    botao.dataset.enviando = "true";
    botao.disabled = true;
    botao.textContent = "Registrando aceite...";

    mostrarMensagem("Registrando seu aceite. Aguarde...");

    try {
        // Obtém o token de segurança da sessão PHP.
        const token = await obterTokenTermo();

        // Envia o aceite ao servidor.
        const dados = await requisitarJSON(
            API_ACEITAR_TERMO,
            {
                method: "POST",

                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": token
                },

                body: JSON.stringify({
                    aceite: true
                })
            }
        );

        if (dados.sucesso !== true) {
            throw new Error(
                dados.erro ||
                dados.mensagem ||
                "O servidor não confirmou o aceite."
            );
        }

        // Usa o perfil retornado pelo PHP ou salvo no login.
        const tipo = normalizarPerfil(
            dados.tipo || localStorage.getItem("tipo")
        );

        const destino = obterPaginaDestino(tipo);

        if (!destino) {
            throw new Error(
                "O aceite foi registrado, mas o perfil do usuário não foi identificado."
            );
        }

        localStorage.setItem("tipo", tipo);

        mostrarMensagem(
            "Termo aceito com sucesso! Redirecionando...",
            "sucesso"
        );

        // Redireciona para a página correspondente ao perfil.
        window.location.replace(destino);

    } catch (erro) {
        console.error("Erro ao aceitar o termo:", erro);

        mostrarMensagem(
            erro.message ||
            "Não foi possível registrar o aceite. Tente novamente.",
            "erro"
        );

        botao.disabled = !checkbox.checked;
        botao.textContent = "Aceitar termo de autorização";
        delete botao.dataset.enviando;
    }
}

// ==========================================================
// 11. INICIALIZAÇÃO E EVENTOS
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    const checkbox = obterCheckbox();
    const botao = obterBotao();

    if (!checkbox || !botao) {
        console.error(
            "Confira no HTML os IDs aceiteTermo e btnAceitar."
        );
        return;
    }

    // Atualiza o estado inicial do botão.
    atualizarBotao();

    // Habilita o botão ao marcar a caixa.
    checkbox.addEventListener("change", atualizarBotao);

    // Registra o aceite ao clicar.
    botao.addEventListener("click", aceitarTermo);

    // Verifica se o usuário já aceitou anteriormente.
    verificarEstadoTermo();
});
