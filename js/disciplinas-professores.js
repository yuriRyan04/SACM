
"use strict";

const $ = (id) => document.getElementById(id);

const campoPesquisa = $("pesquisaProfessor");
const botaoBuscar = $("buscarProfessor");
const botaoMostrarTodos = $("mostrarTodos");
const botaoAdicionar = $("adicionarDisciplinas");
const tabelaProfessores = $("listaProfessores");
const totalProfessores = $("totalProfessores");
const mensagemLista = $("mensagemLista");

function mostrarMensagem(texto, erro = false) {
    mensagemLista.textContent = texto;
    mensagemLista.style.color = erro ? "#ff7777" : "";
}

function escaparHTML(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, (caractere) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[caractere]);
}

async function carregarProfessores(pesquisa = "") {
    tabelaProfessores.innerHTML = `
        <tr><td colspan="4" class="estado-vazio">Carregando...</td></tr>
    `;
    mostrarMensagem("");

    try {
        const url = "./api/listar_professores.php?pesquisa=" +
            encodeURIComponent(pesquisa);

        const resposta = await fetch(url, {
            method: "GET",
            headers: { "Accept": "application/json" },
            cache: "no-store"
        });

        const dados = await resposta.json();

        if (!resposta.ok || !dados.sucesso) {
            throw new Error(dados.mensagem || "Não foi possível carregar os professores.");
        }

        const professores = Array.isArray(dados.professores)
            ? dados.professores : [];

        totalProfessores.textContent = professores.length;

        if (professores.length === 0) {
            tabelaProfessores.innerHTML = `
                <tr><td colspan="4" class="estado-vazio">
                    Nenhum professor encontrado.
                </td></tr>
            `;
            mostrarMensagem("Nenhum resultado para essa pesquisa.");
            return;
        }

        tabelaProfessores.innerHTML = professores.map((professor) => {
            const id = Number(professor.id_professor);
            const matricula = String(professor.matricula ?? "");
            const nome = escaparHTML(professor.nome);
            const disciplinas = escaparHTML(
                professor.disciplinas || "Nenhuma disciplina atribuída"
            );

            return `
                <tr>
                    <td>${nome}</td>
                    <td>${escaparHTML(matricula)}</td>
                    <td>${disciplinas}</td>
                    <td>
                        <button type="button"
                            class="botao botao-principal editar-disciplinas"
                            data-matricula="${escaparHTML(matricula)}"
                            data-id="${id}">
                            EDITAR DISCIPLINAS
                        </button>
                    </td>
                </tr>
            `;
        }).join("");

    } catch (erro) {
        console.error("Erro ao listar professores:", erro);
        tabelaProfessores.innerHTML = `
            <tr><td colspan="4" class="estado-vazio">
                Erro ao carregar os professores.
            </td></tr>
        `;
        totalProfessores.textContent = "0";
        mostrarMensagem(erro.message || "Erro de comunicação com o servidor.", true);
    }
}

function abrirAtribuicao(matricula = "") {
    const destino = "./adicionar-disciplinas-professor.html";
    const url = matricula
        ? `${destino}?matricula=${encodeURIComponent(matricula)}`
        : destino;

    window.location.href = url;
}

botaoBuscar.addEventListener("click", () => {
    carregarProfessores(campoPesquisa.value.trim());
});

campoPesquisa.addEventListener("keydown", (evento) => {
    if (evento.key === "Enter") {
        evento.preventDefault();
        carregarProfessores(campoPesquisa.value.trim());
    }
});

botaoMostrarTodos.addEventListener("click", () => {
    campoPesquisa.value = "";
    carregarProfessores();
});

botaoAdicionar.addEventListener("click", () => {
    abrirAtribuicao();
});

tabelaProfessores.addEventListener("click", (evento) => {
    const botao = evento.target.closest(".editar-disciplinas");
    if (!botao) return;

    abrirAtribuicao(botao.dataset.matricula || "");
});

carregarProfessores();
