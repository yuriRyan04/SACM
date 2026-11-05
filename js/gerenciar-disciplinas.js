
"use strict";

/* =====================================================
   SACM — GERENCIAR DISCIPLINAS
   Lista todas as áreas cadastradas
   Todas as séries/cursos começam desmarcados
===================================================== */

const API = "./api/disciplinas.php";

const area = document.getElementById("area");
const nomeDisciplina = document.getElementById("nomeDisciplina");
const limiteMonitores = document.getElementById("limiteMonitores");
const tabela = document.getElementById("tabelaDisciplinas");
const mensagem = document.getElementById("mensagem");

const campoId = document.getElementById("idDisciplina");
const formulario = document.getElementById("formDisciplina");
const botaoSalvar = document.getElementById("botaoSalvar");
const botaoCancelar = document.getElementById("botaoCancelar");

let editandoId = null;
let temporizadorMensagem;

/* MENSAGENS */

function mostrarMensagem(texto, sucesso = true) {
    if (!mensagem) return;

    mensagem.textContent = texto;
    mensagem.style.color = sucesso ? "#16803d" : "#ff5263";

    clearTimeout(temporizadorMensagem);

    temporizadorMensagem = setTimeout(() => {
        mensagem.textContent = "";
    }, 4000);
}

/* OPÇÕES DE SÉRIES E CURSOS */

function obterMarcados(nome) {
    return Array.from(
        document.querySelectorAll(
            'input[name="' + nome + '"]:checked'
        )
    ).map(campo => campo.value);
}

function atualizarOpcoes(nome, universal) {
    const campos = Array.from(
        document.querySelectorAll(
            'input[name="' + nome + '"]'
        )
    );

    const campoUniversal = campos.find(
        campo => campo.value === universal
    );

    const especificos = campos.filter(
        campo => campo.value !== universal
    );

    if (!campoUniversal) return;

    if (campoUniversal.checked) {
        especificos.forEach(campo => {
            campo.checked = false;
            campo.disabled = true;
        });

        return;
    }

    especificos.forEach(campo => {
        campo.disabled = false;
    });

    if (
        especificos.length > 0 &&
        especificos.every(campo => campo.checked)
    ) {
        campoUniversal.checked = true;

        especificos.forEach(campo => {
            campo.checked = false;
            campo.disabled = true;
        });
    }
}

function configurarOpcoes(nome, universal) {
    document.querySelectorAll(
        'input[name="' + nome + '"]'
    ).forEach(campo => {
        campo.addEventListener("change", () => {
            const campos = document.querySelectorAll(
                'input[name="' + nome + '"]'
            );

            if (campo.value === universal && campo.checked) {
                campos.forEach(outro => {
                    if (outro !== campo) {
                        outro.checked = false;
                    }
                });
            } else if (
                campo.value !== universal &&
                campo.checked
            ) {
                const campoUniversal = document.querySelector(
                    'input[name="' + nome + '"][value="' +
                    universal + '"]'
                );

                if (campoUniversal) {
                    campoUniversal.checked = false;
                }
            }

            atualizarOpcoes(nome, universal);
        });
    });

    atualizarOpcoes(nome, universal);
}

function definirMarcados(nome, valores, universal) {
    const texto = String(valores ?? "").trim();

    const selecionados = texto
        ? texto.split(",").map(valor => valor.trim())
        : [];

    document.querySelectorAll(
        'input[name="' + nome + '"]'
    ).forEach(campo => {
        campo.checked = selecionados.includes(campo.value);
        campo.disabled = false;
    });

    atualizarOpcoes(nome, universal);
}

configurarOpcoes("series", "Todas");
configurarOpcoes("cursos", "Todos");

/* VALIDAÇÃO */

function validarOpcoes() {
    if (obterMarcados("series").length === 0) {
        mostrarMensagem(
            "Selecione pelo menos uma série ou marque Todas as séries.",
            false
        );
        return false;
    }

    if (obterMarcados("cursos").length === 0) {
        mostrarMensagem(
            "Selecione pelo menos um curso ou marque Todos os cursos.",
            false
        );
        return false;
    }

    return true;
}

/* LIMPAR FORMULÁRIO */

function limparFormulario() {
    formulario?.reset();

    editandoId = null;

    if (campoId) campoId.value = "";
    if (nomeDisciplina) nomeDisciplina.value = "";
    if (limiteMonitores) limiteMonitores.value = "2";

    // Nenhuma opção universal ou específica fica marcada.
    ["series", "cursos"].forEach(nome => {
        document.querySelectorAll(
            'input[name="' + nome + '"]'
        ).forEach(campo => {
            campo.checked = false;
            campo.disabled = false;
        });
    });

    if (botaoSalvar) {
        botaoSalvar.textContent = "+ Adicionar disciplina";
        botaoSalvar.disabled = false;
    }

    if (botaoCancelar) {
        botaoCancelar.hidden = true;
    }
}

/* TABELA */

function mostrarLinhaVazia(texto) {
    tabela.innerHTML = "";

    const linha = document.createElement("tr");
    const celula = document.createElement("td");

    celula.colSpan = 7;
    celula.textContent = texto;

    linha.appendChild(celula);
    tabela.appendChild(linha);
}

function adicionarCelula(linha, valor) {
    const celula = document.createElement("td");

    celula.textContent = String(valor ?? "");

    linha.appendChild(celula);
}

/* CARREGAR TODAS AS DISCIPLINAS */

async function carregarDisciplinas() {
    if (!tabela) return;

    mostrarLinhaVazia("Carregando disciplinas...");

    try {
        // Sem filtro de área: consulta todas as disciplinas.
        const resposta = await fetch(API, {
            method: "GET",
            cache: "no-store",
            headers: {
                "Accept": "application/json"
            }
        });

        const texto = await resposta.text();
        let dados;

        try {
            dados = JSON.parse(texto);
        } catch {
            console.error("Resposta da API:", texto);

            throw new Error(
                "O PHP não retornou JSON válido. Verifique a API."
            );
        }

        if (!resposta.ok || !Array.isArray(dados)) {
            throw new Error(
                dados?.mensagem ||
                "Não foi possível consultar as disciplinas."
            );
        }

        if (dados.length === 0) {
            mostrarLinhaVazia(
                "Nenhuma disciplina cadastrada no banco de dados."
            );
            return;
        }

        tabela.innerHTML = "";

        dados.forEach(disciplina => {
            const id = Number(
                disciplina.id_disciplinas ?? disciplina.id
            );

            const linha = document.createElement("tr");

            adicionarCelula(linha, id);

            adicionarCelula(
                linha,
                disciplina.nome ?? disciplina.disciplina ?? ""
            );

            adicionarCelula(linha, disciplina.area ?? "");
            adicionarCelula(linha, disciplina.series || "Todas");
            adicionarCelula(linha, disciplina.cursos || "Todos");

            adicionarCelula(
                linha,
                disciplina.limite_monitores ?? 2
            );

            const acoes = document.createElement("td");

            const editar = document.createElement("button");
            editar.type = "button";
            editar.className = "btn-editar";
            editar.textContent = "Editar";
            editar.addEventListener("click", () => {
                editarDisciplina(disciplina);
            });

            const excluir = document.createElement("button");
            excluir.type = "button";
            excluir.className = "btn-excluir";
            excluir.textContent = "Excluir";
            excluir.addEventListener("click", () => {
                excluirDisciplina(id);
            });

            acoes.append(editar, excluir);
            linha.appendChild(acoes);
            tabela.appendChild(linha);
        });

    } catch (erro) {
        console.error("Erro ao carregar disciplinas:", erro);

        mostrarLinhaVazia(
            "Erro ao carregar as disciplinas. Verifique o servidor."
        );

        mostrarMensagem(erro.message, false);
    }
}

/* CADASTRAR OU EDITAR */

async function adicionarDisciplina() {
    const nome = nomeDisciplina.value.trim();
    const limite = Number(limiteMonitores.value);

    if (!nome) {
        mostrarMensagem("Digite o nome da disciplina.", false);
        nomeDisciplina.focus();
        return;
    }

    if (nome.length > 100) {
        mostrarMensagem(
            "O nome deve ter no máximo 100 caracteres.",
            false
        );
        return;
    }

    if (!area.value) {
        mostrarMensagem("Selecione uma área.", false);
        return;
    }

    if (
        !Number.isInteger(limite) ||
        limite < 1 ||
        limite > 100
    ) {
        mostrarMensagem(
            "O limite deve estar entre 1 e 100.",
            false
        );
        return;
    }

    if (!validarOpcoes()) return;

    const dados = new FormData();

    if (editandoId !== null) {
        dados.append("editar", "1");
        dados.append("id", String(editandoId));
    } else {
        dados.append("adicionar", "1");
    }

    dados.append("nome", nome);
    dados.append("area", area.value);
    dados.append("limite_monitores", String(limite));

    obterMarcados("series").forEach(valor => {
        dados.append("series[]", valor);
    });

    obterMarcados("cursos").forEach(valor => {
        dados.append("cursos[]", valor);
    });

    try {
        botaoSalvar.disabled = true;

        const resposta = await fetch(API, {
            method: "POST",
            body: dados,
            headers: {
                "Accept": "application/json"
            }
        });

        const texto = await resposta.text();
        let resultado;

        try {
            resultado = JSON.parse(texto);
        } catch {
            console.error("Resposta do PHP:", texto);

            throw new Error(
                "O PHP não retornou JSON válido."
            );
        }

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem || "Não foi possível salvar."
            );
        }

        mostrarMensagem(
            resultado.mensagem || "Disciplina salva com sucesso!",
            true
        );

        limparFormulario();
        await carregarDisciplinas();

    } catch (erro) {
        console.error("Erro ao salvar:", erro);
        mostrarMensagem(erro.message, false);

    } finally {
        botaoSalvar.disabled = false;
    }
}

/* EDITAR */

function editarDisciplina(disciplina) {
    const id = Number(
        disciplina.id_disciplinas ?? disciplina.id
    );

    if (!Number.isInteger(id) || id <= 0) {
        mostrarMensagem("ID da disciplina inválido.", false);
        return;
    }

    editandoId = id;
    campoId.value = String(id);

    nomeDisciplina.value =
        disciplina.nome ?? disciplina.disciplina ?? "";

    area.value = disciplina.area ?? "";

    limiteMonitores.value = String(
        disciplina.limite_monitores ?? 2
    );

    definirMarcados(
        "series",
        disciplina.series ?? "",
        "Todas"
    );

    definirMarcados(
        "cursos",
        disciplina.cursos ?? "",
        "Todos"
    );

    botaoSalvar.textContent = "Salvar alterações";
    botaoCancelar.hidden = false;

    mostrarMensagem(
        "Edite os campos e clique em Salvar alterações.",
        true
    );

    formulario.scrollIntoView({
        behavior: "smooth",
        block: "center"
    });

    nomeDisciplina.focus();
}

/* CANCELAR EDIÇÃO */

function cancelarEdicao() {
    limparFormulario();
    mostrarMensagem("Edição cancelada.", true);
}

/* EXCLUIR */

async function excluirDisciplina(id) {
    if (!Number.isInteger(id) || id <= 0) {
        mostrarMensagem("ID da disciplina inválido.", false);
        return;
    }

    if (!confirm("Deseja realmente excluir esta disciplina?")) {
        return;
    }

    const dados = new FormData();
    dados.append("excluir", String(id));

    try {
        const resposta = await fetch(API, {
            method: "POST",
            body: dados,
            headers: {
                "Accept": "application/json"
            }
        });

        const texto = await resposta.text();
        let resultado;

        try {
            resultado = JSON.parse(texto);
        } catch {
            console.error("Resposta do PHP:", texto);
            throw new Error("O PHP não retornou JSON válido.");
        }

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(
                resultado.mensagem || "Não foi possível excluir."
            );
        }

        if (editandoId === id) {
            limparFormulario();
        }

        mostrarMensagem(
            resultado.mensagem || "Disciplina excluída com sucesso!",
            true
        );

        await carregarDisciplinas();

    } catch (erro) {
        console.error("Erro ao excluir:", erro);
        mostrarMensagem(erro.message, false);
    }
}

/* EVENTOS E COMPATIBILIDADE */

botaoCancelar?.addEventListener("click", cancelarEdicao);

window.adicionarDisciplina = adicionarDisciplina;
window.editarDisciplina = editarDisciplina;
window.excluirDisciplina = excluirDisciplina;
window.cancelarEdicao = cancelarEdicao;

/* INICIALIZAÇÃO */

limparFormulario();
carregarDisciplinas();
