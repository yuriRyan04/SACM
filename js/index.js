
"use strict";

// =========================================
// ELEMENTOS DA PÁGINA
// =========================================

const formulario = document.getElementById("formLogin");
const campoMatricula = document.getElementById("matricula");
const campoSenha = document.getElementById("senha");
const campoPerfil = document.getElementById("perfil");
const mensagem = document.getElementById("mensagem");
const botaoEntrar = document.getElementById("botaoEntrar");
const botaoVoltar = document.getElementById("botaoVoltar");
const botaoOlho = document.getElementById("toggleSenha");
const iconeOlho = document.getElementById("iconeOlho");
const botoesPerfil = document.querySelectorAll(".perfil-btn");
const linkCadastro = document.getElementById("linkCadastroProfessor");

let tipoSelecionado = "aluno";
let loginEmAndamento = false;

// =========================================
// SELECIONAR PERFIL
// =========================================

function selecionarPerfil(tipo) {
    const perfisValidos = ["aluno", "professor", "gestao"];

    if (!perfisValidos.includes(tipo)) {
        tipo = "aluno";
    }

    tipoSelecionado = tipo;

    if (campoPerfil) {
        campoPerfil.value = tipo;
    }

    // =========================================
    // LIMITE DE CARACTERES DA MATRÍCULA
    // Somente para o perfil Professor
    // =========================================

    if (campoMatricula) {
        if (tipo === "professor") {
            // Máximo de 8 caracteres.
            campoMatricula.maxLength = 8;

            // Exibe teclado numérico em dispositivos móveis.
            campoMatricula.setAttribute("inputmode", "numeric");

            // Se já houver mais de 8 caracteres,
            // mantém somente os primeiros 8.
            if (campoMatricula.value.length > 8) {
                campoMatricula.value =
                    campoMatricula.value.slice(0, 8);
            }
        } else {
            // Aluno e Gestão não possuem esse limite.
            campoMatricula.removeAttribute("maxlength");
            campoMatricula.removeAttribute("inputmode");
        }
    }

    // Atualizar os botões de seleção de perfil.
    botoesPerfil.forEach((botao) => {
        const selecionado = botao.dataset.perfil === tipo;

        botao.classList.toggle("ativo", selecionado);
        botao.setAttribute("aria-pressed", String(selecionado));
    });

    // Mostrar cadastro somente para Professor.
    if (linkCadastro) {
        linkCadastro.hidden = tipo !== "professor";
    }

    mostrarMensagem("");

    if (campoMatricula) {
        campoMatricula.focus();
    }
}

// =========================================
// MOSTRAR E OCULTAR SENHA
// =========================================

function alternarSenha() {
    if (!campoSenha || !botaoOlho || !iconeOlho) {
        return;
    }

    const mostrar = campoSenha.type === "password";

    campoSenha.type = mostrar ? "text" : "password";

    botaoOlho.setAttribute(
        "aria-label",
        mostrar ? "Ocultar senha" : "Mostrar senha"
    );

    botaoOlho.setAttribute("aria-pressed", String(mostrar));

    if (mostrar) {
        iconeOlho.innerHTML = `
            <path d="M3 3l18 18"/>
            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
            <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5
                     c6.4 0 10 7 10 7s-1.3 2.5-3.8 4.5"/>
            <path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7
                     c1.3 0 2.5-.3 3.5-.7"/>
        `;
    } else {
        iconeOlho.innerHTML = `
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/>
            <circle cx="12" cy="12" r="3"/>
        `;
    }
}

// =========================================
// MENSAGENS
// =========================================

function mostrarMensagem(texto) {
    if (mensagem) {
        mensagem.textContent = texto;
    }
}

function interpretarErro(resposta) {
    const codigo = resposta?.codigo;
    const texto = resposta?.mensagem || resposta?.erro;

    if (texto) {
        return texto;
    }

    switch (codigo) {
        case "CAMPOS_VAZIOS":
        case "campos_obrigatorios":
            return "Preencha a matrícula e a senha.";

        case "USUARIO_NAO_ENCONTRADO":
        case "aluno_nao_encontrado_ldap":
            return "Matrícula não encontrada.";

        case "SENHA_INCORRETA":
        case "login_invalido":
            return "Matrícula ou senha incorretas.";

        case "PERFIL_INVALIDO":
        case "perfil_invalido":
            return "Selecione um perfil válido.";

        case "dados_ldap_incompletos":
            return "O cadastro do usuário está incompleto.";

        case "ldap_indisponivel":
            return "Servidor de autenticação indisponível.";

        case "erro_banco":
            return "Erro ao acessar o banco de dados.";

        default:
            return "Não foi possível realizar o login. Verifique os dados e tente novamente.";
    }
}

// =========================================
// PÁGINA DE DESTINO
// =========================================

function obterPagina(tipo) {
    const paginas = {
        aluno: "./Inicio.html",
        professor: "./Inicio-professor.html",
        gestao: "./Inicio-gestao.html"
    };

    return paginas[tipo] || paginas.aluno;
}

// =========================================
// VERIFICAR TERMO DE AUTORIZAÇÃO
// =========================================

async function verificarTermo() {
    const resposta = await fetch("./api/verificar_termo.php", {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
            "Accept": "application/json"
        }
    });

    let dados;

    try {
        dados = await resposta.json();
    } catch {
        throw new Error(
            "A API de verificação do termo não retornou JSON válido."
        );
    }

    if (!resposta.ok || dados.sucesso !== true) {
        throw new Error(
            "Não foi possível verificar o termo de autorização. " +
            "Confira a sessão PHP e a conexão com o banco de dados."
        );
    }

    return dados;
}

// =========================================
// REALIZAR LOGIN
// =========================================

async function realizarLogin(evento) {
    evento.preventDefault();

    if (loginEmAndamento) {
        return;
    }

    const matricula = campoMatricula.value.trim();
    const senha = campoSenha.value;

    if (!matricula || !senha) {
        mostrarMensagem("Preencha a matrícula e a senha.");
        return;
    }

    // Conferir o limite antes de enviar o login.
    if (
        tipoSelecionado === "professor" &&
        matricula.length > 8
    ) {
        mostrarMensagem(
            "A matrícula do professor deve ter no máximo 8 caracteres."
        );
        campoMatricula.focus();
        return;
    }

    loginEmAndamento = true;
    botaoEntrar.disabled = true;
    botaoEntrar.textContent = "Entrando...";
    mostrarMensagem("");

    try {
        // 1. Autenticar o usuário.
        const respostaHTTP = await fetch("./api/login.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                matricula: matricula,
                senha: senha,
                tipo: tipoSelecionado,
                perfil: tipoSelecionado
            })
        });

        let dados;

        try {
            dados = await respostaHTTP.json();
        } catch {
            throw new Error(
                "O servidor não retornou JSON válido. Verifique api/login.php."
            );
        }

        if (!respostaHTTP.ok || dados.sucesso !== true) {
            mostrarMensagem(interpretarErro(dados));
            return;
        }

        const tipoRetornado = dados.tipo || tipoSelecionado;

        if (!["aluno", "professor", "gestao"].includes(tipoRetornado)) {
            throw new Error("O servidor retornou um perfil inválido.");
        }

        // 2. Guardar os dados não sensíveis usados pela interface.
        // A senha nunca é salva no localStorage.
        localStorage.setItem("matricula", matricula);
        localStorage.setItem("tipo", tipoRetornado);

        if (dados.nome) {
            localStorage.setItem("nome", dados.nome);
        }

        if (dados.serie) {
            localStorage.setItem("serie", dados.serie);
        }

        if (dados.turma) {
            localStorage.setItem("turma", dados.turma);
        }

        if (dados.curso) {
            localStorage.setItem("curso", dados.curso);
        }

        // 3. Verificar o aceite registrado no banco.
        const termo = await verificarTermo();

        // 4. Se ainda não aceitou a versão vigente,
        // encaminhar para a tela do termo.
        if (termo.aceito !== true) {
            window.location.href = "./termo-autorizacao.html";
            return;
        }

        // 5. Se já aceitou, continuar normalmente.
        window.location.href = obterPagina(tipoRetornado);

    } catch (erro) {
        console.error("Erro no login ou na verificação do termo:", erro);

        mostrarMensagem(
            erro.message ||
            "Erro de conexão com o servidor. Tente novamente."
        );
    } finally {
        loginEmAndamento = false;
        botaoEntrar.disabled = false;
        botaoEntrar.innerHTML = '<span aria-hidden="true">↪</span> Entrar';
    }
}

// =========================================
// VOLTAR
// =========================================

function voltar() {
    mostrarMensagem("");

    if (campoSenha) {
        campoSenha.value = "";
        campoSenha.type = "password";
    }

    if (botaoOlho) {
        botaoOlho.setAttribute("aria-label", "Mostrar senha");
        botaoOlho.setAttribute("aria-pressed", "false");
    }

    if (iconeOlho) {
        iconeOlho.innerHTML = `
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/>
            <circle cx="12" cy="12" r="3"/>
        `;
    }

    selecionarPerfil("aluno");
}

// =========================================
// EVENTOS
// =========================================

botoesPerfil.forEach((botao) => {
    botao.addEventListener("click", () => {
        selecionarPerfil(botao.dataset.perfil);
    });
});

if (botaoOlho) {
    botaoOlho.addEventListener("click", alternarSenha);
}

if (formulario) {
    formulario.addEventListener("submit", realizarLogin);
}

if (botaoVoltar) {
    botaoVoltar.addEventListener("click", voltar);
}

// Disponibilizar funções para chamadas pelo HTML.
window.selecionarPerfil = selecionarPerfil;
window.alternarSenha = alternarSenha;
window.voltar = voltar;

// =========================================
// PERFIL INICIAL: ALUNO
// =========================================

selecionarPerfil("aluno");
