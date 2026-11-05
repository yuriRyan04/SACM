
"use strict";

/* =====================================================
   SACM — HUMANAS
   Disciplinas filtradas pela série e curso do aluno
===================================================== */

async function carregarDisciplinas() {
    const lista = document.getElementById("listaDisciplinas");
    const mensagem = document.getElementById("mensagem");

    if (!lista) {
        console.error("Elemento #listaDisciplinas não encontrado.");
        return;
    }

    lista.replaceChildren();

    if (mensagem) {
        mensagem.textContent = "Carregando disciplinas...";
    }

    try {
        const resposta = await fetch(
            "./api/disciplinas_aluno.php?area=" +
            encodeURIComponent("HUMANAS"),
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
                "Não foi possível consultar as disciplinas."
            );
        }

        if (!Array.isArray(dados)) {
            throw new Error(
                dados.mensagem ||
                "A API não retornou uma lista válida."
            );
        }

        if (mensagem) {
            mensagem.textContent = "";
        }

        if (dados.length === 0) {
            const texto =
                "Nenhuma disciplina disponível para sua série e curso.";

            if (mensagem) {
                mensagem.textContent = texto;
            } else {
                lista.textContent = texto;
            }

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
                disciplina.nome || disciplina.disciplina || "Disciplina";

            botao.addEventListener("click", function () {
                window.location.href =
                    "./disciplina.html?id=" +
                    encodeURIComponent(id);
            });

            lista.appendChild(botao);
        });

        if (lista.children.length === 0) {
            const texto =
                "Nenhuma disciplina disponível para sua série e curso.";

            if (mensagem) {
                mensagem.textContent = texto;
            } else {
                lista.textContent = texto;
            }
        }

    } catch (erro) {
        console.error("Erro ao carregar Humanas:", erro);

        lista.replaceChildren();

        if (mensagem) {
            mensagem.textContent =
                erro.message || "Erro ao carregar disciplinas.";
        } else {
            lista.textContent =
                erro.message || "Erro ao carregar disciplinas.";
        }
    }
}

document.addEventListener("DOMContentLoaded", carregarDisciplinas);
