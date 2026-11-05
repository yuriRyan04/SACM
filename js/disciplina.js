
"use strict";

/* =====================================================
   SACM - MONITORES POR DISCIPLINA
===================================================== */

const parametros = new URLSearchParams(window.location.search);
const idDisciplina = parametros.get("id");

const tituloDisciplina = document.getElementById("tituloDisciplina");
const tituloAgendamento = document.getElementById("tituloAgendamento");
const mensagem = document.getElementById("mensagem");
const monitoresConteudo = document.getElementById("monitoresConteudo");
const dadosMonitores = document.getElementById("dadosMonitores");

/* =====================================================
   INICIALIZAÇÃO
===================================================== */

if (
    !tituloDisciplina ||
    !tituloAgendamento ||
    !mensagem ||
    !monitoresConteudo ||
    !dadosMonitores
) {
    console.error(
        "Os elementos HTML da página de monitores não foram encontrados."
    );

} else if (
    !idDisciplina ||
    !/^\d+$/.test(idDisciplina) ||
    Number(idDisciplina) <= 0
) {
    tituloDisciplina.textContent = "DISCIPLINA";
    tituloAgendamento.textContent = "NENHUMA DISCIPLINA SELECIONADA";

    mensagem.textContent =
        "ID da disciplina ausente ou inválido. Volte e selecione uma disciplina.";

} else {
    carregarDisciplina();
}

/* =====================================================
   BUSCAR DISCIPLINA PELO ID
===================================================== */

async function carregarDisciplina() {
    try {
        mensagem.textContent = "Carregando disciplina...";

        const resposta = await fetch(
            "./api/disciplina.php?id=" +
            encodeURIComponent(idDisciplina),
            {
                cache: "no-store"
            }
        );

        if (!resposta.ok) {
            throw new Error("Erro HTTP: " + resposta.status);
        }

        const disciplina = await resposta.json();

        console.log("DISCIPLINA RECEBIDA:", disciplina);

        if (disciplina.sucesso === false) {
            throw new Error(
                disciplina.mensagem ||
                disciplina.erro ||
                "Não foi possível encontrar a disciplina."
            );
        }

        const id = Number(
            disciplina.id_disciplinas || disciplina.id
        );

        const nome =
            disciplina.nome ||
            disciplina.disciplina;

        if (!id || !nome) {
            throw new Error(
                "O servidor não retornou o ID ou o nome da disciplina."
            );
        }

        tituloDisciplina.textContent = nome.toUpperCase();

        tituloAgendamento.textContent =
            "MONITORES DE " + nome.toUpperCase();

        await carregarMonitores(id, nome);

    } catch (erro) {
        console.error("Erro ao carregar disciplina:", erro);

        tituloDisciplina.textContent = "ERRO";
        tituloAgendamento.textContent =
            "ERRO AO CARREGAR DISCIPLINA";

        mensagem.textContent =
            erro.message || "Erro ao carregar a disciplina.";
    }
}

/* =====================================================
   BUSCAR MONITORES DA DISCIPLINA
===================================================== */

async function carregarMonitores(idDisciplina, nomeDisciplina) {
    mensagem.textContent = "Carregando monitores...";
    monitoresConteudo.innerHTML = "";
    dadosMonitores.style.display = "block";

    try {
        const url = new URL(
            "./api/monitores_disciplina.php",
            window.location.href
        );

        // Enviar o ID da disciplina
        url.searchParams.set(
            "id_disciplinas",
            idDisciplina
        );

        const resposta = await fetch(url, {
            cache: "no-store"
        });

        if (!resposta.ok) {
            throw new Error("Erro HTTP: " + resposta.status);
        }

        const dados = await resposta.json();

        console.log("MONITORES RECEBIDOS:", dados);

        if (dados.sucesso === false) {
            throw new Error(
                dados.mensagem ||
                dados.erro ||
                "Erro ao buscar os monitores."
            );
        }

        const monitores = Array.isArray(dados)
            ? dados
            : (dados.monitores || dados.dados || []);

        if (
            !Array.isArray(monitores) ||
            monitores.length === 0
        ) {
            mensagem.textContent =
                "Nenhum monitor cadastrado para esta disciplina.";

            return;
        }

        mensagem.textContent = "";

        monitores.forEach(function (monitor) {
            // ID do monitor
            const idMonitor =
                monitor.id_monitor ||
                monitor.id_monitores ||
                monitor.id;

            // Nome do monitor
            const nomeMonitor =
                monitor.nome ||
                monitor.Nome ||
                monitor.nome_aluno ||
                monitor.nome_monitor ||
                "Nome não informado";

            // Série
            const serieMonitor =
                monitor.serie ||
                monitor.Serie ||
                monitor.serie_aluno ||
                "Série não informada";

            // Turma
            const turmaMonitor =
                monitor.turma ||
                monitor.Turma ||
                monitor.turma_aluno ||
                "Turma não informada";

            // Curso
            const cursoMonitor =
                monitor.curso ||
                monitor.Curso ||
                monitor.curso_aluno ||
                "Curso não informado";

            // Criar botão do monitor
            const botao = document.createElement("button");

            botao.type = "button";
            botao.className = "botao-monitor";

            // Exibir nome
            const nome = document.createElement("span");

            nome.className = "nome-monitor";
            nome.textContent = nomeMonitor;

            // Reunir série, turma e curso em um único campo
            const turma = document.createElement("span");

            turma.className = "turma-monitor";
            turma.textContent =
                "Turma: " +
                serieMonitor + " " +
                turmaMonitor + " " +
                cursoMonitor;

            // Adicionar nome e informações ao cartão
            botao.append(nome, turma);

            // Validar ID do monitor
            if (
                idMonitor === undefined ||
                idMonitor === null ||
                idMonitor === "" ||
                !Number.isFinite(Number(idMonitor)) ||
                Number(idMonitor) <= 0
            ) {
                botao.disabled = true;
                botao.title = "Monitor sem ID válido.";

            } else {
                botao.addEventListener("click", function () {
                    abrirAgendamento(
                        idMonitor,
                        nomeMonitor,
                        idDisciplina,
                        nomeDisciplina
                    );
                });
            }

            // Exibir cartão
            monitoresConteudo.appendChild(botao);
        });

    } catch (erro) {
        console.error("Erro ao carregar monitores:", erro);

        mensagem.textContent =
            erro.message ||
            "Erro ao carregar os monitores.";
    }
}

/* =====================================================
   ABRIR PÁGINA DE AGENDAMENTO
===================================================== */

function abrirAgendamento(
    idMonitor,
    nomeMonitor,
    idDisciplina,
    nomeDisciplina
) {
    const url = new URL(
        "agendamentos.html",
        window.location.href
    );

    // Dados do monitor
    url.searchParams.set("monitor", idMonitor);
    url.searchParams.set("nomeMonitor", nomeMonitor);

    // ID numérico da disciplina
    url.searchParams.set(
        "id_disciplinas",
        idDisciplina
    );

    // Nome da disciplina
    url.searchParams.set(
        "disciplina",
        nomeDisciplina
    );

    // Redirecionar para a página de agendamento
    window.location.href = url.href;
}
