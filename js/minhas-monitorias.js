"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const matricula = localStorage.getItem("matricula");

    const totalAgendamentos = document.getElementById("totalAgendamentos");
    const totalAgendados = document.getElementById("totalAgendados");
    const totalRealizados = document.getElementById("totalRealizados");
    const quantidadeDisciplinas = document.getElementById("quantidadeDisciplinas");

    const listaDisciplinas = document.getElementById("listaDisciplinas");
    const listaMonitorias = document.getElementById("listaMonitorias");
    const mensagem = document.getElementById("mensagem");

    let disciplinas = [];
    let agendamentos = [];
    let disciplinaSelecionada = null;

    if (!listaDisciplinas || !listaMonitorias || !mensagem) {
        console.error("Não foram encontrados os elementos HTML necessários.");
        return;
    }

    if (!matricula) {
        mostrarMensagem(
            "Não foi possível identificar sua matrícula. Entre novamente no sistema.",
            true
        );
        return;
    }

    carregarMonitorias();

    /* =====================================================
       CARREGAR MONITORIAS
    ===================================================== */

    async function carregarMonitorias() {
        mostrarMensagem("Carregando monitorias...");

        try {
            const resposta = await fetch(
                "./api/minhas-monitorias.php?matricula=" +
                encodeURIComponent(matricula),
                { cache: "no-store" }
            );

            const dados = await resposta.json();

            if (!resposta.ok || !dados.sucesso) {
                throw new Error(
                    dados.mensagem || "Erro ao carregar monitorias."
                );
            }

            disciplinas = Array.isArray(dados.disciplinas)
                ? dados.disciplinas
                : [];

            agendamentos = Array.isArray(dados.agendamentos)
                ? dados.agendamentos
                : [];

            console.log("Matrícula consultada:", matricula);
            console.log("Disciplinas recebidas:", disciplinas);
            console.log("Agendamentos recebidos:", agendamentos);

            atualizarContadores();
            renderizarDisciplinas();

            if (disciplinas.length > 0) {
                selecionarDisciplina(
                    disciplinas[0].id_disciplinas
                );
            } else {
                listaMonitorias.innerHTML = "";
                mostrarMensagem(
                    "Nenhuma disciplina encontrada para esta matrícula."
                );
            }
        } catch (erro) {
            console.error("Erro ao carregar monitorias:", erro);

            mostrarMensagem(
                erro.message ||
                "Não foi possível carregar as monitorias.",
                true
            );
        }
    }

    /* =====================================================
       FORMATAR SÉRIE, TURMA E CURSO
    ===================================================== */

    function formatarTurmaAluno(agendamento) {
        const serie =
            agendamento.aluno_serie ??
            agendamento.serie ??
            agendamento.Serie ??
            agendamento.série ??
            agendamento.Série ??
            "";

        const turma =
            agendamento.aluno_turma ??
            agendamento.turma ??
            agendamento.Turma ??
            "";

        const curso =
            agendamento.aluno_curso ??
            agendamento.curso ??
            agendamento.Curso ??
            "";

        return [serie, turma, curso]
            .map(valor => String(valor ?? "").trim())
            .filter(valor => valor !== "")
            .join(" ");
    }

    /* =====================================================
       ATUALIZAR CONTADORES
    ===================================================== */

    function atualizarContadores() {
        const total = agendamentos.length;

        const realizados = agendamentos.filter(
            item => normalizar(item.status) === "realizado"
        ).length;

        const agendados = agendamentos.filter(
            item => normalizar(item.status) === "agendado"
        ).length;

        if (totalAgendamentos) {
            totalAgendamentos.textContent = total;
        }

        if (totalAgendados) {
            totalAgendados.textContent = agendados;
        }

        if (totalRealizados) {
            totalRealizados.textContent = realizados;
        }

        if (quantidadeDisciplinas) {
            quantidadeDisciplinas.textContent =
                `${disciplinas.length} ${
                    disciplinas.length === 1
                        ? "disciplina"
                        : "disciplinas"
                }`;
        }
    }

    /* =====================================================
       RENDERIZAR DISCIPLINAS
    ===================================================== */

    function renderizarDisciplinas() {
        listaDisciplinas.innerHTML = "";

        disciplinas.forEach(disciplina => {
            const botao = document.createElement("button");

            botao.type = "button";
            botao.className = "card-disciplina";

            const id = disciplina.id_disciplinas;
            const nome = disciplina.disciplina || "Disciplina";
            const area = disciplina.area || "";

            botao.dataset.id = String(id);

            if (
                String(id) === String(disciplinaSelecionada)
            ) {
                botao.classList.add("disciplina-selecionada");
            }

            const nomeElemento = document.createElement("strong");
            nomeElemento.className = "nome-disciplina";
            nomeElemento.textContent = nome;

            const areaElemento = document.createElement("span");
            areaElemento.className = "area-disciplina";
            areaElemento.textContent = area;

            botao.append(nomeElemento, areaElemento);

            botao.addEventListener("click", () => {
                selecionarDisciplina(id);
            });

            listaDisciplinas.appendChild(botao);
        });
    }

    /* =====================================================
       SELECIONAR DISCIPLINA E LISTAR AGENDAMENTOS
    ===================================================== */

    function selecionarDisciplina(idDisciplina) {
        disciplinaSelecionada = idDisciplina;

        renderizarDisciplinas();

        const disciplina = disciplinas.find(
            item =>
                String(item.id_disciplinas) ===
                String(idDisciplina)
        );

        if (!disciplina) {
            listaMonitorias.innerHTML = "";
            mostrarMensagem("Disciplina não encontrada.");
            return;
        }

        const encontrados = agendamentos.filter(item => {
            const idAgendamentoDisciplina =
                item.id_disciplinas ??
                item.id_disciplina_salvo ??
                item.id_disciplina_banco;

            if (
                idAgendamentoDisciplina !== undefined &&
                idAgendamentoDisciplina !== null &&
                String(idAgendamentoDisciplina) ===
                String(idDisciplina)
            ) {
                return true;
            }

            const nomeAgendamento = normalizar(
                item.disciplina_banco ||
                item.disciplina_monitor ||
                ""
            );

            return (
                nomeAgendamento !== "" &&
                nomeAgendamento === normalizar(disciplina.disciplina)
            );
        });

        listaMonitorias.innerHTML = "";

        const titulo = document.createElement("h2");
        titulo.className = "titulo-agendamentos";
        titulo.textContent =
            `Agendamentos — ${disciplina.disciplina}`;

        listaMonitorias.appendChild(titulo);

        if (encontrados.length === 0) {
            const aviso = document.createElement("p");
            aviso.className = "aviso-vazio";
            aviso.textContent =
                "Ainda não existem agendamentos para esta disciplina.";

            listaMonitorias.appendChild(aviso);
            return;
        }

        encontrados.forEach(agendamento => {
            listaMonitorias.appendChild(
                criarCartaoAgendamento(agendamento)
            );
        });
    }

    /* =====================================================
       CRIAR CARTÃO DO AGENDAMENTO
    ===================================================== */

    function criarCartaoAgendamento(agendamento) {
        const card = document.createElement("article");
        card.className = "card-agendamento";

        const nomeAluno = document.createElement("h3");
        nomeAluno.className = "nome-aluno";
        nomeAluno.textContent =
            agendamento.aluno_nome || "Aluno";

        card.appendChild(nomeAluno);

        /* Série + Turma + Curso */

        const turmaCompleta = formatarTurmaAluno(agendamento);

        adicionarCampo(
            card,
            "Turma:",
            turmaCompleta || "Informações não disponíveis"
        );

        adicionarCampo(
            card,
            "Data:",
            formatarData(agendamento.data_agendamento)
        );

        adicionarCampo(
            card,
            "Horário:",
            agendamento.hora_inicio
        );

        adicionarCampo(
            card,
            "Assunto:",
            agendamento.assunto
        );

        adicionarCampo(
            card,
            "Observação:",
            agendamento.observacao
        );

        const statusAtual =
            normalizar(agendamento.status) || "agendado";

        const linhaStatus = document.createElement("p");
        linhaStatus.className = "campo-agendamento";

        const rotuloStatus = document.createElement("strong");
        rotuloStatus.textContent = "Status: ";

        const valorStatus = document.createElement("span");
        valorStatus.className = "status-agendamento";
        valorStatus.textContent = capitalizar(statusAtual);
        valorStatus.dataset.status = statusAtual;

        linhaStatus.append(rotuloStatus, valorStatus);
        card.appendChild(linhaStatus);

        if (statusAtual === "agendado") {
            const botaoRealizado =
                document.createElement("button");

            botaoRealizado.type = "button";
            botaoRealizado.className = "btn-realizado";
            botaoRealizado.textContent =
                "✓ Marcar como realizado";

            botaoRealizado.addEventListener("click", async () => {
                await marcarComoRealizado(
                    agendamento,
                    botaoRealizado
                );
            });

            card.appendChild(botaoRealizado);

        } else if (statusAtual === "realizado") {
            const etiqueta = document.createElement("span");
            etiqueta.className = "etiqueta-realizado";
            etiqueta.textContent = "✓ Monitoria realizada";

            card.appendChild(etiqueta);
        }

        return card;
    }

    /* =====================================================
       ADICIONAR CAMPO AO CARTÃO
    ===================================================== */

    function adicionarCampo(card, rotulo, valor) {
        if (
            valor === undefined ||
            valor === null ||
            String(valor).trim() === ""
        ) {
            return;
        }

        const paragrafo = document.createElement("p");
        paragrafo.className = "campo-agendamento";

        const strong = document.createElement("strong");
        strong.textContent = `${rotulo} `;

        const texto = document.createElement("span");
        texto.textContent = valor;

        paragrafo.append(strong, texto);
        card.appendChild(paragrafo);
    }

    /* =====================================================
       MARCAR COMO REALIZADO
    ===================================================== */

    async function marcarComoRealizado(agendamento, botao) {
        if (!agendamento.id_agendamento) {
            alert("O ID deste agendamento não foi encontrado.");
            return;
        }

        const confirmar = window.confirm(
            "Deseja marcar esta monitoria como realizada?"
        );

        if (!confirmar) return;

        botao.disabled = true;
        botao.textContent = "Atualizando...";

        try {
            const resposta = await fetch(
                "./api/atualizar-status-monitoria.php",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        id_agendamento:
                            agendamento.id_agendamento
                    }),
                    cache: "no-store"
                }
            );

            const dados = await resposta.json();

            if (!resposta.ok || !dados.sucesso) {
                throw new Error(
                    dados.mensagem ||
                    "Não foi possível atualizar o status."
                );
            }

            agendamento.status = "realizado";

            atualizarContadores();
            selecionarDisciplina(disciplinaSelecionada);

            console.log(
                dados.mensagem || "Monitoria realizada."
            );

        } catch (erro) {
            console.error(
                "Erro ao marcar como realizado:",
                erro
            );

            alert(
                erro.message ||
                "Erro ao atualizar a monitoria."
            );

            botao.disabled = false;
            botao.textContent = "✓ Marcar como realizado";
        }
    }

    /* =====================================================
       MENSAGENS
    ===================================================== */

    function mostrarMensagem(texto, erro = false) {
        mensagem.textContent = texto;
        mensagem.classList.toggle("mensagem-erro", erro);
        mensagem.hidden = false;
    }

    /* =====================================================
       NORMALIZAR TEXTO
    ===================================================== */

    function normalizar(valor) {
        return String(valor ?? "")
            .trim()
            .toLocaleLowerCase("pt-BR")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "");
    }

    /* =====================================================
       CAPITALIZAR TEXTO
    ===================================================== */

    function capitalizar(texto) {
        const valor = String(texto || "");

        return (
            valor.charAt(0).toLocaleUpperCase("pt-BR") +
            valor.slice(1)
        );
    }

    /* =====================================================
       FORMATAR DATA
    ===================================================== */

    function formatarData(data) {
        if (!data) return "";

        const valor = String(data);

        const correspondencia =
            valor.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (correspondencia) {
            return (
                `${correspondencia[3]}/` +
                `${correspondencia[2]}/` +
                `${correspondencia[1]}`
            );
        }

        return valor;
    }
});