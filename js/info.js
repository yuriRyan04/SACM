
"use strict";

/* =====================================================
   SACM — BASE TÉCNICA / INFORMÁTICA
   Filtro automático por série e curso do aluno
===================================================== */

async function carregarDisciplinas() {
    const lista = document.getElementById("listaDisciplinas");

    if (!lista) {
        console.error("Elemento #listaDisciplinas não encontrado.");
        return;
    }

    lista.textContent = "Carregando disciplinas...";

    try {
        const resposta = await fetch(
            "./api/disciplinas_aluno.php?area=" +
            encodeURIComponent("Base Técnica / INFORMÁTICA"),
            {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        const dados = await resposta.json();

        if (!resposta.ok) {
            throw new Error(
                dados.mensagem ||
                "Não foi possível carregar as disciplinas."
            );
        }

        if (!Array.isArray(dados)) {
            throw new Error(
                dados.mensagem ||
                "A resposta da API é inválida."
            );
        }

        lista.replaceChildren();

        if (dados.length === 0) {
            lista.textContent =
                "Nenhuma disciplina disponível para sua série e curso.";
            return;
        }

        dados.forEach(function (disciplina) {
            const id = Number(
                disciplina.id_disciplinas || disciplina.id
            );

            if (!Number.isInteger(id) || id <= 0) {
                return;
            }

            const botao = document.createElement("button");

            botao.type = "button";
            botao.className = "botao-disciplina";
            botao.textContent =
                disciplina.nome ||
                disciplina.disciplina ||
                "Disciplina";

            botao.addEventListener("click", function () {
                window.location.href =
                    "./disciplina.html?id=" +
                    encodeURIComponent(id);
            });

            lista.appendChild(botao);
        });

        if (lista.children.length === 0) {
            lista.textContent =
                "Nenhuma disciplina disponível para sua série e curso.";
        }

    } catch (erro) {
        console.error("Erro ao carregar Informática:", erro);

        lista.textContent =
            erro.message || "Erro ao carregar disciplinas.";
    }
}

document.addEventListener("DOMContentLoaded", carregarDisciplinas);
