
"use strict";

const parametros = new URLSearchParams(window.location.search);
const idDisciplina = parametros.get("id");

const API = "./api/meus-monitores.php";

const lista = document.getElementById("listaMonitores");
const pesquisa = document.getElementById("pesquisa");
const filtro = document.getElementById("filtroDisciplina");
const totalMonitores = document.getElementById("totalMonitores");
const totalDisciplinas = document.getElementById("totalDisciplinas");
const btnAtualizar = document.getElementById("btnAtualizar");
const tituloDisciplina = document.getElementById("tituloDisciplina");

let monitores = [];

function escaparHTML(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, caractere => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[caractere]);
}

function mostrarMensagem(texto) {
    lista.innerHTML = `
        <tr>
            <td colspan="4" class="mensagem-lista">
                ${escaparHTML(texto)}
            </td>
        </tr>
    `;
}

async function carregarMonitores() {
    btnAtualizar.disabled = true;

    try {
        if (!idDisciplina || !/^\d+$/.test(idDisciplina)) {
            throw new Error(
                "Selecione uma disciplina em Minhas Disciplinas."
            );
        }

        mostrarMensagem("Carregando monitores...");

        const resposta = await fetch(
            `${API}?id=${encodeURIComponent(idDisciplina)}`,
            {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            }
        );

        const texto = await resposta.text();
        let dados;

        try {
            dados = JSON.parse(texto);
        } catch {
            console.error("Resposta original da API:", texto);

            throw new Error(
                "Resposta inválida do servidor. Verifique o log PHP."
            );
        }

        if (!resposta.ok || !dados.sucesso) {
            throw new Error(
                dados.mensagem || `Erro HTTP ${resposta.status}.`
            );
        }

        monitores = Array.isArray(dados.monitores)
            ? dados.monitores
            : [];

        const disciplina = dados.disciplina;

        if (disciplina) {
            tituloDisciplina.textContent =
                "Monitores de " + disciplina.nome;

            document.title = "SACM - " + disciplina.nome;

            filtro.innerHTML = "";

            const opcao = document.createElement("option");
            opcao.value = disciplina.nome;
            opcao.textContent = disciplina.nome;

            filtro.appendChild(opcao);
            filtro.disabled = true;
        }

        atualizarResumo();
        renderizarMonitores();

    } catch (erro) {
        console.error("Erro ao carregar monitores:", erro);

        monitores = [];

        atualizarResumo();

        mostrarMensagem(
            erro.message || "Erro ao carregar os monitores."
        );

    } finally {
        btnAtualizar.disabled = false;
    }
}

function atualizarResumo() {
    totalMonitores.textContent = monitores.length;

    totalDisciplinas.textContent =
        monitores.length > 0 ? "1" : "0";
}

function renderizarMonitores() {
    const termo = pesquisa.value
        .trim()
        .toLocaleLowerCase("pt-BR");

    const filtrados = monitores.filter(monitor => {
        const valores = [
            monitor.nome,
            monitor.matricula,
            monitor.turma,
            monitor.disciplina
        ];

        return valores.some(valor =>
            String(valor ?? "")
                .toLocaleLowerCase("pt-BR")
                .includes(termo)
        );
    });

    if (filtrados.length === 0) {
        mostrarMensagem(
            monitores.length > 0
                ? "Nenhum monitor corresponde à pesquisa."
                : "Nenhum monitor cadastrado nesta disciplina."
        );

        return;
    }

    lista.innerHTML = filtrados.map(monitor => `
        <tr>
            <td>
                ${escaparHTML(monitor.nome || "Não informado")}
            </td>

            <td>
                ${escaparHTML(monitor.matricula || "Não informada")}
            </td>

            <td>
                ${escaparHTML(monitor.turma || "Não informada")}
            </td>

            <td>
                <span class="disciplina-etiqueta">
                    ${escaparHTML(
                        monitor.disciplina || "Não informada"
                    )}
                </span>
            </td>
        </tr>
    `).join("");
}

pesquisa.addEventListener("input", renderizarMonitores);
btnAtualizar.addEventListener("click", carregarMonitores);

carregarMonitores();
