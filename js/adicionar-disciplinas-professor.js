
"use strict";

const $ = (id) => document.getElementById(id);

const campoMatricula = $("matriculaProfessor");
const botaoBuscar = $("buscarProfessorMatricula");
const mensagemBusca = $("mensagemBusca");
const dadosProfessor = $("dadosProfessor");
const painelDisciplinas = $("painelDisciplinas");
const nomeProfessor = $("nomeProfessor");
const matriculaEncontrada = $("matriculaEncontrada");
const iniciaisProfessor = $("iniciaisProfessor");
const filtroArea = $("filtroArea");
const pesquisaDisciplina = $("pesquisaDisciplina");
const listaDisciplinas = $("listaDisciplinas");
const contadorDisciplinas = $("contadorDisciplinas");
const mensagemDisciplinas = $("mensagemDisciplinas");
const botaoSelecionarVisiveis = $("selecionarVisiveis");
const botaoLimparSelecao = $("limparSelecao");
const botaoSalvar = $("salvarDisciplinas");
const botaoCancelar = $("cancelarEdicao");

const API = "./api/gerenciar_disciplinas_professor.php";

let professorAtual = null;
let disciplinas = [];
let disciplinasSelecionadas = new Set();

function escaparHTML(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[c]);
}

function mostrarMensagem(elemento, texto, erro = false) {
    if (!elemento) return;

    elemento.textContent = texto;
    elemento.style.color = erro ? "#ff7777" : "";
}

async function requisitar(url, opcoes = {}) {
    const resposta = await fetch(url, {
        cache: "no-store",
        credentials: "same-origin",
        ...opcoes,
        headers: {
            "Accept": "application/json",
            ...(opcoes.headers || {})
        }
    });

    const texto = await resposta.text();
    let dados;

    try {
        dados = JSON.parse(texto);
    } catch {
        console.error("Resposta recebida da API:", texto);

        throw new Error(
            `A API não retornou JSON válido (HTTP ${resposta.status}). Confira o PHP.`
        );
    }

    if (!resposta.ok || dados.sucesso !== true) {
        throw new Error(
            dados.mensagem ||
            `A operação falhou (HTTP ${resposta.status}).`
        );
    }

    return dados;
}

function atualizarContador() {
    contadorDisciplinas.textContent =
        disciplinasSelecionadas.size;
}

function renderizarDisciplinas() {
    const termo = pesquisaDisciplina.value
        .trim()
        .toLocaleLowerCase("pt-BR");

    const area = filtroArea.value;

    const filtradas = disciplinas.filter((disciplina) => {
        const nome = String(disciplina.disciplina || "")
            .toLocaleLowerCase("pt-BR");

        const nomeArea = String(disciplina.area || "");

        return nome.includes(termo) &&
            (!area || nomeArea === area);
    });

    if (!filtradas.length) {
        listaDisciplinas.innerHTML =
            '<p class="estado-vazio">Nenhuma disciplina encontrada.</p>';

        atualizarContador();
        return;
    }

    listaDisciplinas.innerHTML = filtradas.map((disciplina) => {
        const id = String(disciplina.id_disciplinas);

        const marcada = disciplinasSelecionadas.has(id)
            ? "checked"
            : "";

        const series = disciplina.series || "Não informadas";
        const cursos = disciplina.cursos || "Não informados";

        const limite =
            disciplina.limite_monitores === null ||
            disciplina.limite_monitores === undefined ||
            disciplina.limite_monitores === ""
                ? "Não informado"
                : disciplina.limite_monitores;

        return `
            <label class="item-disciplina">
                <input
                    type="checkbox"
                    class="checkbox-disciplina"
                    value="${escaparHTML(id)}"
                    ${marcada}
                >

                <span class="informacoes-disciplina">
                    <strong class="nome-disciplina">
                        ${escaparHTML(disciplina.disciplina)}
                    </strong>

                    <small>
                        <b>Área:</b>
                        ${escaparHTML(disciplina.area || "Não informada")}
                    </small>

                    <small>
                        <b>Série(s):</b>
                        ${escaparHTML(series)}
                    </small>

                    <small>
                        <b>Curso(s):</b>
                        ${escaparHTML(cursos)}
                    </small>

                    <small>
                        <b>Limite de monitores:</b>
                        ${escaparHTML(limite)}
                    </small>
                </span>
            </label>
        `;
    }).join("");

    atualizarContador();
}

async function carregarAreas() {
    const dados = await requisitar(`${API}?acao=areas`);

    filtroArea.innerHTML =
        '<option value="">Todas as áreas</option>';

    (dados.areas || []).forEach((area) => {
        const opcao = document.createElement("option");

        opcao.value = area;
        opcao.textContent = area;

        filtroArea.appendChild(opcao);
    });
}

async function carregarDisciplinas() {
    const dados = await requisitar(`${API}?acao=disciplinas`);

    disciplinas = dados.disciplinas || [];

    renderizarDisciplinas();
}

/* =========================================
   BUSCAR PROFESSOR PELA MATRÍCULA
========================================= */

async function buscarProfessor() {
    const matricula = campoMatricula.value.trim();

    if (!matricula) {
        mostrarMensagem(
            mensagemBusca,
            "Digite a matrícula do professor.",
            true
        );

        campoMatricula.focus();
        return;
    }

    botaoBuscar.disabled = true;

    mostrarMensagem(mensagemBusca, "Pesquisando professor...");

    dadosProfessor.hidden = true;
    painelDisciplinas.hidden = true;
    professorAtual = null;

    try {
        const url =
            `${API}?acao=buscar_professor&matricula=${encodeURIComponent(matricula)}`;

        const dados = await requisitar(url);
        const professor = dados.professor;

        if (
            !professor ||
            !professor.id_professor ||
            !professor.nome ||
            professor.matricula === undefined
        ) {
            throw new Error(
                "A API não retornou os dados completos do professor."
            );
        }

        professorAtual = professor;

        campoMatricula.value = professor.matricula;
        nomeProfessor.textContent = professor.nome;
        matriculaEncontrada.textContent = professor.matricula;

        iniciaisProfessor.textContent = String(professor.nome)
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((parte) => parte[0])
            .join("")
            .toLocaleUpperCase("pt-BR");

        disciplinasSelecionadas = new Set(
            (professor.disciplinas_ids || []).map(String)
        );

        await carregarAreas();
        await carregarDisciplinas();

        dadosProfessor.hidden = false;
        painelDisciplinas.hidden = false;

        mostrarMensagem(mensagemBusca, "Professor encontrado.");

        mostrarMensagem(
            mensagemDisciplinas,
            "Confira as informações, marque as disciplinas desejadas e clique em SALVAR DISCIPLINAS."
        );

    } catch (erro) {
        console.error("Erro ao buscar professor:", erro);

        mostrarMensagem(
            mensagemBusca,
            erro.message || "Não foi possível buscar o professor.",
            true
        );

        dadosProfessor.hidden = true;
        painelDisciplinas.hidden = true;

    } finally {
        botaoBuscar.disabled = false;
    }
}

botaoBuscar.addEventListener("click", buscarProfessor);

campoMatricula.addEventListener("keydown", (evento) => {
    if (evento.key === "Enter") {
        evento.preventDefault();
        buscarProfessor();
    }
});

/* =========================================
   FILTROS E SELEÇÃO
========================================= */

filtroArea.addEventListener("change", renderizarDisciplinas);

pesquisaDisciplina.addEventListener("input", renderizarDisciplinas);

listaDisciplinas.addEventListener("change", (evento) => {
    const checkbox = evento.target.closest(".checkbox-disciplina");

    if (!checkbox) return;

    const id = String(checkbox.value);

    if (checkbox.checked) {
        disciplinasSelecionadas.add(id);
    } else {
        disciplinasSelecionadas.delete(id);
    }

    atualizarContador();
});

botaoSelecionarVisiveis.addEventListener("click", () => {
    const termo = pesquisaDisciplina.value
        .trim()
        .toLocaleLowerCase("pt-BR");

    const area = filtroArea.value;

    disciplinas.forEach((disciplina) => {
        const nome = String(disciplina.disciplina || "")
            .toLocaleLowerCase("pt-BR");

        const nomeArea = String(disciplina.area || "");

        if (
            nome.includes(termo) &&
            (!area || nomeArea === area)
        ) {
            disciplinasSelecionadas.add(
                String(disciplina.id_disciplinas)
            );
        }
    });

    renderizarDisciplinas();
});

botaoLimparSelecao.addEventListener("click", () => {
    disciplinasSelecionadas.clear();
    renderizarDisciplinas();
});

/* =========================================
   SALVAR DISCIPLINAS DO PROFESSOR
========================================= */

botaoSalvar.addEventListener("click", async () => {
    if (!professorAtual) {
        mostrarMensagem(
            mensagemDisciplinas,
            "Pesquise um professor primeiro.",
            true
        );
        return;
    }

    botaoSalvar.disabled = true;

    mostrarMensagem(
        mensagemDisciplinas,
        "Salvando disciplinas..."
    );

    try {
        const dados = await requisitar(API, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                acao: "salvar",
                id_professor: professorAtual.id_professor,
                disciplinas: Array.from(disciplinasSelecionadas)
            })
        });

        mostrarMensagem(
            mensagemDisciplinas,
            dados.mensagem || "Disciplinas salvas com sucesso."
        );

    } catch (erro) {
        console.error("Erro ao salvar disciplinas:", erro);

        mostrarMensagem(
            mensagemDisciplinas,
            erro.message || "Não foi possível salvar as disciplinas.",
            true
        );

    } finally {
        botaoSalvar.disabled = false;
    }
});

/* =========================================
   VOLTAR
========================================= */

botaoCancelar.addEventListener("click", () => {
    window.location.href =
        "./gerenciar-disciplinas-professores.html";
});

/* =========================================
   MATRÍCULA RECEBIDA PELA URL
========================================= */

const parametros = new URLSearchParams(window.location.search);
const matriculaInicial = parametros.get("matricula");

if (matriculaInicial) {
    campoMatricula.value = matriculaInicial;
    buscarProfessor();
}
