
"use strict";

/* =====================================================
   SACM — CADASTRO E GERENCIAMENTO DE MONITORES
   + CERTIFICADOS DE MONITORIA
===================================================== */

const $ = id => document.getElementById(id);

let alunoEncontrado = null;
let carregandoDisciplinas = false;
let disciplinasCarregadas = [];
let monitoresCertificados = [];

/* =====================================================
   ELEMENTOS DO HTML
===================================================== */

// Cadastro de monitor
const matriculaInput = $("matriculaMonitor");
const dadosAluno = $("dadosAluno");
const nomeAluno = $("nomeAluno");
const serieAluno = $("serieAluno");
const turmaAluno = $("turmaAluno");
const cursoAluno = $("cursoAluno");
const matriculaAluno = $("matriculaAluno");

const campoDisciplina = $("campoDisciplina");
const disciplinaSelect = $("disciplinaMonitor");
const filtroDisciplina = $("filtroDisciplina");

const cartaoDisciplina = $("informacoesDisciplina");
const infoNomeDisciplina = $("infoNomeDisciplina");
const infoAreaDisciplina = $("infoAreaDisciplina");
const infoSeriesDisciplina = $("infoSeriesDisciplina");
const infoCursosDisciplina = $("infoCursosDisciplina");
const infoLimiteDisciplina = $("infoLimiteDisciplina");

const btnCadastrar = $("btnCadastrar");
const btnBuscar = $("btnBuscar");

const mensagem = $("mensagem");
const mensagemLista = $("mensagemLista");
const listaMonitores = $("listaMonitores");

// Certificados
const certificadoDisciplina = $("certificadoDisciplina");
const cargaHorariaCertificado = $("cargaHorariaCertificado");
const periodoCertificado = $("periodoCertificado");
const btnCarregarCertificados = $("btnCarregarCertificados");
const btnBaixarTodosCertificados = $("btnBaixarTodosCertificados");
const listaCertificados = $("listaCertificados");
const mensagemCertificados = $("mensagemCertificados");

/* =====================================================
   MENSAGENS
===================================================== */

function mostrarMensagem(texto, sucesso = false) {
    if (!mensagem) return;

    mensagem.textContent = texto;
    mensagem.style.color = sucesso ? "#b9ffd0" : "#ffbcbc";
}

function mostrarMensagemLista(texto, sucesso = false) {
    if (!mensagemLista) return;

    mensagemLista.textContent = texto;
    mensagemLista.style.color = sucesso ? "#b9ffd0" : "#ffbcbc";
}

function mensagemCertificado(texto, sucesso = false) {
    if (!mensagemCertificados) return;

    mensagemCertificados.textContent = texto;
    mensagemCertificados.style.color = sucesso ? "#b9ffd0" : "#ffbcbc";
}

/* =====================================================
   REQUISIÇÕES JSON
===================================================== */

async function requisicaoJSON(url, opcoes = {}) {
    let resposta;

    try {
        resposta = await fetch(url, {
            cache: "no-store",
            credentials: "same-origin",
            ...opcoes
        });
    } catch (erro) {
        console.error("Falha de comunicação:", erro);

        throw new Error(
            "Não foi possível conectar ao servidor do SACM."
        );
    }

    const texto = await resposta.text();

    if (!texto.trim()) {
        throw new Error(
            `O servidor retornou HTTP ${resposta.status} sem conteúdo.`
        );
    }

    let dados;

    try {
        dados = JSON.parse(texto);
    } catch (erro) {
        console.error("Resposta recebida do PHP:", texto);

        throw new Error(
            `O PHP não retornou JSON válido (HTTP ${resposta.status}).`
        );
    }

    if (!resposta.ok || dados.sucesso === false) {
        throw new Error(
            dados.mensagem ||
            dados.erro ||
            `Erro HTTP ${resposta.status}`
        );
    }

    return dados;
}

/* =====================================================
   FORMATAR INFORMAÇÕES
===================================================== */

function formatarInformacao(
    valor,
    alternativa = "Não informado"
) {
    if (valor === null || valor === undefined) {
        return alternativa;
    }

    if (Array.isArray(valor)) {
        const itens = valor
            .map(item => String(item).trim())
            .filter(Boolean);

        return itens.length ? itens.join(", ") : alternativa;
    }

    const texto = String(valor).trim();

    if (!texto || texto.toLowerCase() === "null") {
        return alternativa;
    }

    if (texto.startsWith("[") && texto.endsWith("]")) {
        try {
            const lista = JSON.parse(texto);

            if (Array.isArray(lista)) {
                return formatarInformacao(lista, alternativa);
            }
        } catch (erro) {
            // Mantém o texto original.
        }
    }

    return texto;
}

function escaparHTML(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, caractere => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[caractere]);
}

/* =====================================================
   INFORMAÇÕES DA DISCIPLINA
===================================================== */

function limparInformacoesDisciplina() {
    if (cartaoDisciplina) {
        cartaoDisciplina.style.display = "none";
    }

    if (infoNomeDisciplina) infoNomeDisciplina.textContent = "-";
    if (infoAreaDisciplina) infoAreaDisciplina.textContent = "-";
    if (infoSeriesDisciplina) infoSeriesDisciplina.textContent = "-";
    if (infoCursosDisciplina) infoCursosDisciplina.textContent = "-";
    if (infoLimiteDisciplina) infoLimiteDisciplina.textContent = "-";
}

function exibirInformacoesDisciplina() {
    if (!disciplinaSelect || !disciplinaSelect.value) {
        limparInformacoesDisciplina();
        return;
    }

    const idSelecionado = String(disciplinaSelect.value);

    const disciplina = disciplinasCarregadas.find(item => {
        const id =
            item.id_disciplinas ??
            item.id_disciplinas_fk ??
            item.id ??
            item.disciplina_id;

        return id != null && String(id) === idSelecionado;
    });

    if (!disciplina) {
        limparInformacoesDisciplina();

        mostrarMensagem(
            "Não foi possível encontrar os dados dessa disciplina."
        );

        return;
    }

    if (infoNomeDisciplina) {
        infoNomeDisciplina.textContent = formatarInformacao(
            disciplina.disciplina ??
            disciplina.nome ??
            disciplina.nome_disciplina
        );
    }

    if (infoAreaDisciplina) {
        infoAreaDisciplina.textContent =
            formatarInformacao(disciplina.area);
    }

    if (infoSeriesDisciplina) {
        infoSeriesDisciplina.textContent = formatarInformacao(
            disciplina.series ?? disciplina.serie
        );
    }

    if (infoCursosDisciplina) {
        infoCursosDisciplina.textContent = formatarInformacao(
            disciplina.cursos ?? disciplina.curso
        );
    }

    if (infoLimiteDisciplina) {
        const limite = disciplina.limite_monitores;

        infoLimiteDisciplina.textContent =
            limite === null || limite === undefined || limite === ""
                ? "Não definido"
                : String(limite);
    }

    if (cartaoDisciplina) {
        cartaoDisciplina.style.display = "block";
    }
}

/* =====================================================
   LIMPAR DADOS DO ALUNO
===================================================== */

function limparAluno() {
    alunoEncontrado = null;

    if (dadosAluno) dadosAluno.style.display = "none";
    if (campoDisciplina) campoDisciplina.style.display = "none";

    if (btnCadastrar) {
        btnCadastrar.style.display = "none";
        btnCadastrar.disabled = false;
    }

    if (disciplinaSelect) disciplinaSelect.value = "";

    limparInformacoesDisciplina();

    if (nomeAluno) nomeAluno.textContent = "";
    if (serieAluno) serieAluno.textContent = "";
    if (turmaAluno) turmaAluno.textContent = "";
    if (cursoAluno) cursoAluno.textContent = "";
    if (matriculaAluno) matriculaAluno.textContent = "";
}

/* =====================================================
   BUSCAR ALUNO PELA MATRÍCULA
===================================================== */

async function buscarAluno() {
    if (!matriculaInput) {
        mostrarMensagem("Campo de matrícula não encontrado.");
        return;
    }

    const matricula = matriculaInput.value.trim();

    if (!matricula) {
        limparAluno();
        mostrarMensagem("Digite a matrícula do aluno.");
        matriculaInput.focus();
        return;
    }

    limparAluno();
    mostrarMensagem("Buscando aluno...");

    if (btnBuscar) btnBuscar.disabled = true;

    try {
        const url =
            "./api/buscar_aluno.php?matricula=" +
            encodeURIComponent(matricula);

        const dados = await requisicaoJSON(url);
        const aluno = dados.aluno || dados.dados || dados;

        const nome =
            aluno.nome ??
            aluno.Nome ??
            aluno.nome_aluno ??
            "";

        const serie = aluno.serie ?? aluno.Serie ?? "";
        const turma = aluno.turma ?? aluno.Turma ?? "";
        const curso = aluno.curso ?? aluno.Curso ?? "";

        const matriculaRetornada = aluno.matricula ?? matricula;

        if (!nome) {
            throw new Error(
                "A API não retornou o nome do aluno."
            );
        }

        alunoEncontrado = {
            matricula: String(matriculaRetornada),
            nome: String(nome),
            serie: String(serie),
            turma: String(turma),
            curso: String(curso)
        };

        if (nomeAluno) nomeAluno.textContent = alunoEncontrado.nome;

        if (serieAluno) {
            serieAluno.textContent =
                alunoEncontrado.serie || "Não informada";
        }

        if (turmaAluno) {
            turmaAluno.textContent =
                alunoEncontrado.turma || "Não informada";
        }

        if (cursoAluno) {
            cursoAluno.textContent =
                alunoEncontrado.curso || "Não informado";
        }

        if (matriculaAluno) {
            matriculaAluno.textContent =
                alunoEncontrado.matricula;
        }

        if (dadosAluno) dadosAluno.style.display = "block";
        if (campoDisciplina) campoDisciplina.style.display = "block";

        mostrarMensagem("Aluno encontrado com sucesso!", true);

    } catch (erro) {
        console.error("Erro ao buscar aluno:", erro);

        limparAluno();
        mostrarMensagem(erro.message);

    } finally {
        if (btnBuscar) btnBuscar.disabled = false;
    }
}

/* =====================================================
   CARREGAR E PREENCHER DISCIPLINAS
===================================================== */

function obterListaDisciplinas(dados) {
    if (Array.isArray(dados)) return dados;
    if (Array.isArray(dados.disciplinas)) return dados.disciplinas;
    if (Array.isArray(dados.dados)) return dados.dados;

    return [];
}

function preencherSelect(select, disciplinas) {
    if (!select) return;

    select.replaceChildren();

    const inicial = document.createElement("option");
    inicial.value = "";
    inicial.textContent = "Selecione uma disciplina";

    select.appendChild(inicial);

    disciplinas.forEach(disciplina => {
        const id =
            disciplina.id_disciplinas ??
            disciplina.id_disciplinas_fk ??
            disciplina.id ??
            disciplina.disciplina_id;

        const nome =
            disciplina.disciplina ??
            disciplina.nome ??
            disciplina.nome_disciplina;

        if (id == null || !nome) return;

        const option = document.createElement("option");

        option.value = String(id);
        option.textContent = disciplina.area
            ? `${nome} — ${disciplina.area}`
            : nome;

        select.appendChild(option);
    });
}

async function carregarDisciplinas() {
    if (carregandoDisciplinas) return;

    carregandoDisciplinas = true;

    try {
        const dados = await requisicaoJSON(
            "./api/disciplinas_monitor.php"
        );

        const disciplinas = obterListaDisciplinas(dados);

        disciplinasCarregadas = disciplinas.filter(disciplina => {
            const id =
                disciplina.id_disciplinas ??
                disciplina.id_disciplinas_fk ??
                disciplina.id ??
                disciplina.disciplina_id;

            const nome =
                disciplina.disciplina ??
                disciplina.nome ??
                disciplina.nome_disciplina;

            return id != null && nome;
        });

        preencherSelect(disciplinaSelect, disciplinasCarregadas);
        preencherSelect(filtroDisciplina, disciplinasCarregadas);

        if (disciplinasCarregadas.length) {
            mostrarMensagemLista(
                "Selecione uma disciplina para consultar os monitores.",
                true
            );
        } else {
            mostrarMensagemLista(
                "Nenhuma disciplina foi encontrada."
            );
        }

    } catch (erro) {
        console.error("Erro ao carregar disciplinas:", erro);

        mostrarMensagem(
            "Erro ao carregar disciplinas: " + erro.message
        );

        mostrarMensagemLista(
            "Não foi possível carregar as disciplinas."
        );

    } finally {
        carregandoDisciplinas = false;
    }
}

/* =====================================================
   CADASTRAR MONITOR
===================================================== */

async function cadastrarMonitor() {
    if (!alunoEncontrado) {
        mostrarMensagem("Busque um aluno antes de cadastrar.");
        return;
    }

    if (!disciplinaSelect || !disciplinaSelect.value) {
        mostrarMensagem("Selecione uma disciplina.");
        return;
    }

    const disciplinaId = Number(disciplinaSelect.value);

    if (!Number.isInteger(disciplinaId) || disciplinaId <= 0) {
        mostrarMensagem("A disciplina selecionada é inválida.");
        return;
    }

    if (btnCadastrar) btnCadastrar.disabled = true;

    mostrarMensagem("Cadastrando monitor...");

    try {
        const dados = await requisicaoJSON(
            "./api/cadastrar_monitor.php",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json; charset=UTF-8"
                },
                body: JSON.stringify({
                    matricula: alunoEncontrado.matricula,
                    disciplina_id: disciplinaId
                })
            }
        );

        mostrarMensagem(
            dados.mensagem || "Monitor cadastrado com sucesso!",
            true
        );

        if (filtroDisciplina) {
            filtroDisciplina.value = String(disciplinaId);
            await listarMonitores();
        }

    } catch (erro) {
        console.error("Erro ao cadastrar monitor:", erro);
        mostrarMensagem(erro.message);

    } finally {
        if (btnCadastrar) btnCadastrar.disabled = false;
    }
}

/* =====================================================
   LISTAR MONITORES
===================================================== */

async function listarMonitores() {
    if (!listaMonitores || !filtroDisciplina) return;

    const disciplinaId = filtroDisciplina.value;

    listaMonitores.replaceChildren();

    if (!disciplinaId) {
        listaMonitores.textContent =
            "Selecione uma disciplina para consultar os monitores.";

        mostrarMensagemLista("");
        return;
    }

    mostrarMensagemLista("Carregando monitores...");

    try {
        const dados = await requisicaoJSON(
            "./api/listar_monitores.php?disciplina_id=" +
            encodeURIComponent(disciplinaId)
        );

        const monitores = Array.isArray(dados.monitores)
            ? dados.monitores
            : Array.isArray(dados.dados)
                ? dados.dados
                : Array.isArray(dados)
                    ? dados
                    : [];

        listaMonitores.replaceChildren();

        if (!monitores.length) {
            listaMonitores.textContent =
                "Nenhum monitor cadastrado nesta disciplina.";

            mostrarMensagemLista("");
            return;
        }

        monitores.forEach(monitor => {
            const card = document.createElement("div");
            card.className = "card-monitor";

            const informacoes = document.createElement("div");
            informacoes.className = "informacoes-monitor";

            const nome = document.createElement("h3");
            nome.textContent =
                monitor.nome_aluno ||
                monitor.Nome ||
                monitor.nome ||
                "Nome não disponível";

            const matricula = document.createElement("p");
            matricula.textContent =
                "Matrícula: " +
                (monitor.matricula || "Não informada");

            const disciplina = document.createElement("p");
            disciplina.textContent =
                "Disciplina: " +
                (monitor.disciplina || "Não informada");

            const quantidade = document.createElement("p");
            quantidade.className = "quantidade-monitorias";
            quantidade.textContent =
                "Monitorias cadastradas: " +
                (Number(monitor.quantidade_monitorias) || 0);

            informacoes.append(
                nome,
                matricula,
                disciplina,
                quantidade
            );

            const excluir = document.createElement("button");
            excluir.type = "button";
            excluir.className = "btn-excluir";
            excluir.textContent = "EXCLUIR";

            excluir.addEventListener("click", () => {
                excluirMonitor(monitor.id_monitor, excluir);
            });

            card.append(informacoes, excluir);
            listaMonitores.appendChild(card);
        });

        mostrarMensagemLista(
            `${monitores.length} monitor(es) encontrado(s).`,
            true
        );

    } catch (erro) {
        console.error("Erro ao listar monitores:", erro);

        listaMonitores.textContent =
            "Erro ao carregar a lista de monitores.";

        mostrarMensagemLista(erro.message);
    }
}

/* =====================================================
   EXCLUIR MONITOR
===================================================== */

async function excluirMonitor(idMonitor, botao) {
    if (!idMonitor) {
        mostrarMensagemLista(
            "Não foi possível identificar o monitor."
        );
        return;
    }

    if (!window.confirm(
        "Deseja realmente excluir este monitor desta disciplina?"
    )) {
        return;
    }

    botao.disabled = true;
    botao.textContent = "EXCLUINDO...";

    try {
        const dados = await requisicaoJSON(
            "./api/excluir_monitor.php",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json; charset=UTF-8"
                },
                body: JSON.stringify({
                    id_monitor: Number(idMonitor)
                })
            }
        );

        mostrarMensagemLista(
            dados.mensagem || "Monitor excluído com sucesso!",
            true
        );

        await listarMonitores();

    } catch (erro) {
        console.error("Erro ao excluir monitor:", erro);

        mostrarMensagemLista(erro.message);

        botao.disabled = false;
        botao.textContent = "EXCLUIR";
    }
}

/* =====================================================
   CERTIFICADOS — CARREGAR DISCIPLINAS
===================================================== */

function preencherDisciplinasCertificados(disciplinas) {
    if (!certificadoDisciplina) return;

    certificadoDisciplina.replaceChildren();

    const inicial = document.createElement("option");
    inicial.value = "";
    inicial.textContent = "Selecione uma disciplina";

    certificadoDisciplina.appendChild(inicial);

    disciplinas.forEach(disciplina => {
        if (!disciplina.id_disciplinas || !disciplina.disciplina) {
            return;
        }

        const opcao = document.createElement("option");
        opcao.value = String(disciplina.id_disciplinas);

        opcao.textContent = disciplina.area
            ? `${disciplina.disciplina} — ${disciplina.area}`
            : disciplina.disciplina;

        certificadoDisciplina.appendChild(opcao);
    });
}

async function carregarDisciplinasCertificados() {
    if (!certificadoDisciplina) return;

    try {
        const dados = await requisicaoJSON(
            "./api/certificados.php?acao=disciplinas"
        );

        const disciplinas = Array.isArray(dados.disciplinas)
            ? dados.disciplinas
            : [];

        preencherDisciplinasCertificados(disciplinas);

        if (!disciplinas.length) {
            mensagemCertificado("Nenhuma disciplina foi encontrada.");
        }

    } catch (erro) {
        console.error(
            "Erro ao carregar disciplinas para certificados:",
            erro
        );

        mensagemCertificado(
            "Não foi possível carregar as disciplinas: " + erro.message
        );
    }
}

/* =====================================================
   CERTIFICADOS — ATUALIZAR VISUALIZAÇÃO AUTOMATICAMENTE
===================================================== */

function atualizarVisualizacaoCertificados() {
    if (!listaCertificados) return;

    const carga = cargaHorariaCertificado?.value.trim() || "";
    const periodo = periodoCertificado?.value.trim() || "";

    const cards = listaCertificados.querySelectorAll(
        ".card-certificado"
    );

    cards.forEach(card => {
        const status = card.querySelector(".status-certificado");

        if (!status) return;

        status.classList.remove("emitido", "pendente");
        status.classList.add("pendente");

        if (carga && periodo) {
            status.textContent =
                `Valores atuais do formulário — ${carga} horas — ` +
                `Semestre: ${periodo} (ainda não salvos)`;
        } else if (carga) {
            status.textContent =
                `Carga horária atual: ${carga} horas — ` +
                "Selecione o semestre.";
        } else if (periodo) {
            status.textContent =
                `Semestre atual: ${periodo} — ` +
                "Informe a carga horária.";
        } else {
            status.textContent =
                "Informe a carga horária e o período.";
        }
    });
}

/* =====================================================
   CERTIFICADOS — CONSULTAR MONITORES
===================================================== */

async function consultarMonitoresCertificados() {
    if (!certificadoDisciplina || !certificadoDisciplina.value) {
        mensagemCertificado("Selecione uma disciplina.");
        return;
    }

    if (!listaCertificados) return;

    listaCertificados.replaceChildren();
    monitoresCertificados = [];

    mensagemCertificado("Consultando monitores...");

    if (btnCarregarCertificados) {
        btnCarregarCertificados.disabled = true;
    }

    try {
        const url =
            "./api/certificados.php?acao=monitores&id_disciplinas=" +
            encodeURIComponent(certificadoDisciplina.value);

        const dados = await requisicaoJSON(url);

        monitoresCertificados = Array.isArray(dados.monitores)
            ? dados.monitores
            : [];

        if (!monitoresCertificados.length) {
            listaCertificados.textContent =
                "Nenhum monitor cadastrado nesta disciplina.";

            mensagemCertificado("");
            return;
        }

        monitoresCertificados.forEach(monitor => {
            const card = document.createElement("article");
            card.className = "card-monitor card-certificado";

            const info = document.createElement("div");
            info.className = "informacoes-monitor";

            const nome = document.createElement("h3");
            nome.textContent =
                monitor.nome_aluno ||
                monitor.Nome ||
                monitor.nome ||
                "Nome não disponível";

            const matricula = document.createElement("p");
            matricula.textContent =
                "Matrícula: " +
                (
                    monitor.matricula_aluno ||
                    monitor.matricula_monitor ||
                    monitor.matricula ||
                    "Não informada"
                );

            const disciplina = document.createElement("p");
            disciplina.textContent =
                "Disciplina: " +
                (monitor.disciplina || "Não informada");

            const status = document.createElement("p");
            status.className = "status-certificado";

            if (monitor.id_certificado) {
                status.classList.add("emitido");
                status.textContent =
                    `Últimos dados salvos — ${monitor.carga_horaria} horas — ` +
                    `Período: ${monitor.periodo}`;
            } else {
                status.textContent =
                    "Dados do certificado ainda não salvos.";
            }

            info.append(nome, matricula, disciplina, status);

            const botao = document.createElement("button");
            botao.type = "button";
            botao.className = "btn-certificado";
            botao.textContent = "SALVAR E GERAR PDF";

            botao.addEventListener("click", () => {
                salvarEGerarCertificado(monitor, botao);
            });

            card.append(info, botao);
            listaCertificados.appendChild(card);
        });

        // Aplica os valores atuais do formulário logo após
        // carregar os cartões, sem exigir o salvamento.
        atualizarVisualizacaoCertificados();

        mensagemCertificado(
            `${monitoresCertificados.length} monitor(es) encontrado(s).`,
            true
        );

    } catch (erro) {
        console.error(
            "Erro ao consultar monitores para certificados:",
            erro
        );

        mensagemCertificado(erro.message);

        listaCertificados.textContent =
            "Não foi possível carregar os monitores.";

    } finally {
        if (btnCarregarCertificados) {
            btnCarregarCertificados.disabled = false;
        }
    }
}

/* =====================================================
   CERTIFICADOS — VALIDAR DADOS
===================================================== */

function validarDadosCertificado() {
    const carga = Number(cargaHorariaCertificado?.value);
    const periodo = periodoCertificado?.value.trim() || "";

    if (
        !Number.isInteger(carga) ||
        carga < 1 ||
        carga > 10000
    ) {
        throw new Error(
            "Informe uma carga horária válida entre 1 e 10000 horas."
        );
    }

    if (!periodo || periodo.length > 100) {
        throw new Error(
            "Informe o semestre da monitoria, com até 100 caracteres."
        );
    }

    if (!certificadoDisciplina?.value) {
        throw new Error("Selecione uma disciplina.");
    }

    return { carga, periodo };
}

/* =====================================================
   CERTIFICADOS — SALVAR DADOS NO PHP
===================================================== */

async function salvarDadosCertificado(monitor, carga, periodo) {
    if (!monitor.id_monitor) {
        throw new Error(
            "Não foi possível identificar o monitor selecionado."
        );
    }

    const dadosFormulario = new URLSearchParams();

    dadosFormulario.set("acao", "salvar");
    dadosFormulario.set(
        "id_monitor",
        String(monitor.id_monitor)
    );
    dadosFormulario.set(
        "id_disciplinas",
        String(certificadoDisciplina.value)
    );
    dadosFormulario.set("carga_horaria", String(carga));
    dadosFormulario.set("periodo", periodo);

    return requisicaoJSON("./api/certificados.php", {
        method: "POST",
        headers: {
            "Content-Type":
                "application/x-www-form-urlencoded; charset=UTF-8"
        },
        body: dadosFormulario.toString()
    });
}

/* =====================================================
   CERTIFICADOS — GERAR PDF INDIVIDUAL
===================================================== */

async function salvarEGerarCertificado(monitor, botao) {
    let janelaPDF = null;

    try {
        const { carga, periodo } = validarDadosCertificado();

        const url =
            "./api/gerar_certificado.php?id_monitor=" +
            encodeURIComponent(monitor.id_monitor) +
            "&id_disciplinas=" +
            encodeURIComponent(certificadoDisciplina.value);

        janelaPDF = window.open("about:blank", "_blank");

        if (botao) {
            botao.disabled = true;
            botao.textContent = "SALVANDO...";
        }

        await salvarDadosCertificado(monitor, carga, periodo);

        if (janelaPDF) {
            janelaPDF.location.href = url;
        } else {
            mensagemCertificado(
                "Dados salvos. O navegador bloqueou a nova janela. " +
                "Permita pop-ups para abrir o PDF."
            );
        }

        if (janelaPDF) {
            mensagemCertificado(
                "Dados salvos. Abrindo o certificado PDF.",
                true
            );
        }

        // Consulta novamente os dados persistidos no banco.
        await consultarMonitoresCertificados();

    } catch (erro) {
        if (janelaPDF && !janelaPDF.closed) {
            janelaPDF.close();
        }

        console.error("Erro ao gerar certificado:", erro);
        mensagemCertificado(erro.message);

    } finally {
        if (botao) {
            botao.disabled = false;
            botao.textContent = "SALVAR E GERAR PDF";
        }
    }
}

/* =====================================================
   CERTIFICADOS — SALVAR E BAIXAR TODOS EM ZIP
===================================================== */

async function salvarEBaixarTodosCertificados() {
    if (!monitoresCertificados.length) {
        await consultarMonitoresCertificados();

        if (!monitoresCertificados.length) {
            return;
        }
    }

    let dados;

    try {
        dados = validarDadosCertificado();
    } catch (erro) {
        mensagemCertificado(erro.message);
        return;
    }

    const confirmar = window.confirm(
        `Serão salvos os dados de ${monitoresCertificados.length} ` +
        "certificado(s) da disciplina selecionada. Deseja continuar?"
    );

    if (!confirmar) return;

    if (btnBaixarTodosCertificados) {
        btnBaixarTodosCertificados.disabled = true;
        btnBaixarTodosCertificados.textContent = "PREPARANDO ZIP...";
    }

    if (btnCarregarCertificados) {
        btnCarregarCertificados.disabled = true;
    }

    try {
        const total = monitoresCertificados.length;

        for (let i = 0; i < total; i++) {
            mensagemCertificado(
                `Salvando certificado ${i + 1} de ${total}...`
            );

            await salvarDadosCertificado(
                monitoresCertificados[i],
                dados.carga,
                dados.periodo
            );
        }

        mensagemCertificado(
            "Dados salvos. Solicitando o arquivo ZIP..."
        );

        const url =
            "./api/baixar_certificados.php?id_disciplinas=" +
            encodeURIComponent(certificadoDisciplina.value);

        window.location.href = url;

    } catch (erro) {
        console.error(
            "Erro ao preparar certificados em lote:",
            erro
        );

        mensagemCertificado(
            "Não foi possível preparar o ZIP: " + erro.message
        );

    } finally {
        if (btnBaixarTodosCertificados) {
            btnBaixarTodosCertificados.disabled = false;
            btnBaixarTodosCertificados.textContent =
                "SALVAR E BAIXAR TODOS (ZIP)";
        }

        if (btnCarregarCertificados) {
            btnCarregarCertificados.disabled = false;
        }
    }
}

/* =====================================================
   EVENTOS
===================================================== */

if (btnBuscar) {
    btnBuscar.addEventListener("click", buscarAluno);
}

if (matriculaInput) {
    matriculaInput.addEventListener("keydown", evento => {
        if (evento.key === "Enter") {
            evento.preventDefault();
            buscarAluno();
        }
    });
}

if (disciplinaSelect) {
    disciplinaSelect.addEventListener("change", () => {
        limparInformacoesDisciplina();

        if (disciplinaSelect.value) {
            exibirInformacoesDisciplina();
        }

        if (btnCadastrar) {
            btnCadastrar.style.display =
                disciplinaSelect.value ? "block" : "none";
        }

        mostrarMensagem("");
    });
}

if (btnCadastrar) {
    btnCadastrar.addEventListener("click", cadastrarMonitor);
}

if (filtroDisciplina) {
    filtroDisciplina.addEventListener("change", listarMonitores);
}

if (btnCarregarCertificados) {
    btnCarregarCertificados.addEventListener(
        "click",
        consultarMonitoresCertificados
    );
}

if (certificadoDisciplina) {
    certificadoDisciplina.addEventListener("change", () => {
        monitoresCertificados = [];

        if (listaCertificados) {
            listaCertificados.textContent =
                "Clique em “CONSULTAR MONITORES” para carregar os certificados.";
        }

        mensagemCertificado("");
    });
}

// Atualiza a lista enquanto os valores do formulário mudam.
if (cargaHorariaCertificado) {
    cargaHorariaCertificado.addEventListener(
        "input",
        atualizarVisualizacaoCertificados
    );
}

if (periodoCertificado) {
    periodoCertificado.addEventListener(
        "input",
        atualizarVisualizacaoCertificados
    );

    periodoCertificado.addEventListener(
        "change",
        atualizarVisualizacaoCertificados
    );
}

if (btnBaixarTodosCertificados) {
    btnBaixarTodosCertificados.addEventListener(
        "click",
        salvarEBaixarTodosCertificados
    );
}

/* =====================================================
   INICIALIZAÇÃO
===================================================== */

document.addEventListener("DOMContentLoaded", () => {
    limparInformacoesDisciplina();

    carregarDisciplinas();
    carregarDisciplinasCertificados();
});
