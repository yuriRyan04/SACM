"use strict";


/* =====================================================
   SACM - AGENDAMENTO DE MONITORIAS
===================================================== */


const parametrosAgendamento =
    new URLSearchParams(window.location.search);


const idMonitorSelecionado =
    parametrosAgendamento.get("monitor");


const nomeMonitorSelecionado =
    parametrosAgendamento.get("nomeMonitor");


const disciplinaSelecionada =
    parametrosAgendamento.get("disciplina");


const horariosPadrao = [
    "07:00",
    "07:50",
    "08:40",
    "09:30",
    "10:20",
    "11:10",
    "13:00",
    "13:50",
    "14:40",
    "15:30",
    "16:20"
];


let consultaHorarios = 0;


function elemento(id) {
    return document.getElementById(id);
}


function adicionarCache(url) {
    if (!url) return url;


    const separador = url.includes("?") ? "&" : "?";
    return url + separador + "_=" + Date.now();
}


/* =====================================================
   MENSAGENS
===================================================== */


function mostrarMensagem(texto, sucesso = false) {
    const mensagem = elemento("mensagem");


    if (!mensagem) return;


    mensagem.textContent = texto;
    mensagem.className = sucesso
        ? "mensagem sucesso"
        : "mensagem erro";


    mensagem.style.display = "block";
}


/* =====================================================
   CARREGAR MONITOR SELECIONADO
===================================================== */


async function carregarMonitorSelecionado() {
    const nome = elemento("nomeMonitorSelecionado");
    const disciplina = elemento("disciplinaMonitorSelecionado");
    const foto = elemento("fotoMonitorSelecionado");


    if (nome) {
        nome.textContent =
            nomeMonitorSelecionado || "Monitor selecionado";
    }


    if (disciplina) {
        disciplina.textContent =
            disciplinaSelecionada || "Disciplina";
    }


    if (foto) {
        foto.src = "./img/aluno.png";


        foto.onerror = function () {
            this.onerror = null;
            this.src = "./img/aluno.png";
        };
    }


    if (!idMonitorSelecionado) {
        mostrarMensagem(
            "Monitor não identificado. Volte à disciplina e selecione um monitor."
        );
        return;
    }


    try {
        const resposta = await fetch(
            "./api/monitor_detalhes.php?id_monitor=" +
            encodeURIComponent(idMonitorSelecionado),
            { cache: "no-store" }
        );


        const dados = await resposta.json();


        if (!resposta.ok || dados.sucesso === false) {
            throw new Error(
                dados.mensagem || "Não foi possível carregar o monitor."
            );
        }


        if (nome) {
            nome.textContent =
                dados.nome || nomeMonitorSelecionado || "Monitor";
        }


        if (disciplina) {
            disciplina.textContent =
                dados.disciplina || disciplinaSelecionada || "Disciplina";
        }


        if (foto && dados.foto) {
            foto.src = adicionarCache(dados.foto);
        }
    } catch (erro) {
        console.error("Erro ao carregar monitor:", erro);
    }
}


/* =====================================================
   CARREGAR PERFIL DO ALUNO
===================================================== */


async function carregarPerfilAluno() {
    try {
        const matricula = localStorage.getItem("matricula");


        if (!matricula) {
            console.warn("Matrícula do aluno não encontrada.");
            return;
        }


        const resposta = await fetch(
            "./api/alunos.php?matricula=" +
            encodeURIComponent(matricula),
            { cache: "no-store" }
        );


        if (!resposta.ok) {
            throw new Error("Erro HTTP: " + resposta.status);
        }


        const dados = await resposta.json();


        if (dados.sucesso === false) {
            throw new Error(
                dados.mensagem || "Não foi possível carregar o aluno."
            );
        }


        const nomeUsuario = elemento("nomeUsuario");
        const turmaUsuario = elemento("turmaUsuario");
        const matriculaUsuario = elemento("matriculaUsuario");
        const fotoUsuario = elemento("fotoUsuario");


        if (nomeUsuario) {
            nomeUsuario.textContent = dados.nome || dados.Nome || "Aluno";
        }


        if (turmaUsuario) {
            turmaUsuario.textContent = dados.turma || dados.Turma || "";
        }


        if (matriculaUsuario) {
            matriculaUsuario.textContent = dados.matricula || matricula;
        }


        if (fotoUsuario) {
            fotoUsuario.onerror = function () {
                this.onerror = null;
                this.src = "./img/aluno.png";
            };


            fotoUsuario.src = dados.foto
                ? adicionarCache(dados.foto)
                : "./img/aluno.png";
        }
    } catch (erro) {
        console.error("Erro ao carregar perfil:", erro);
    }
}


/* =====================================================
   DATA MÍNIMA
===================================================== */


function configurarDataMinima() {
    const campoData = elemento("data");


    if (!campoData) return;


    const hoje = new Date();


    const ano = hoje.getFullYear();
    const mes = String(hoje.getMonth() + 1).padStart(2, "0");
    const dia = String(hoje.getDate()).padStart(2, "0");


    campoData.min = `${ano}-${mes}-${dia}`;
}


/* =====================================================
   CONSULTAR HORÁRIOS DISPONÍVEIS
===================================================== */


async function carregarHorariosDisponiveis() {
    const campoData = elemento("data");
    const campoHorario = elemento("horario");
    const ajuda = elemento("ajudaHorario");


    if (!campoData || !campoHorario) return;


    const data = campoData.value;
    const consultaAtual = ++consultaHorarios;


    campoHorario.replaceChildren();


    const opcaoInicial = document.createElement("option");
    opcaoInicial.value = "";
    opcaoInicial.textContent = "Selecione o horário";
    campoHorario.appendChild(opcaoInicial);


    campoHorario.disabled = true;


    if (!idMonitorSelecionado) {
        opcaoInicial.textContent = "Monitor não identificado";
        return;
    }


    if (!data) {
        opcaoInicial.textContent = "Selecione primeiro a data";


        if (ajuda) {
            ajuda.textContent =
                "Selecione uma data para consultar a disponibilidade.";
        }


        return;
    }


    if (campoData.min && data < campoData.min) {
        opcaoInicial.textContent = "Data anterior a hoje";


        mostrarMensagem(
            "A data da monitoria não pode ser anterior a hoje."
        );


        return;
    }


    opcaoInicial.textContent = "Consultando disponibilidade...";


    if (ajuda) {
        ajuda.textContent = "Consultando horários do monitor...";
    }


    try {
        const url =
            "./api/horarios_disponiveis.php" +
            "?id_monitor=" +
            encodeURIComponent(idMonitorSelecionado) +
            "&data=" +
            encodeURIComponent(data);


        const resposta = await fetch(url, {
            cache: "no-store",
            headers: {
                "Accept": "application/json"
            }
        });


        const resultado = await resposta.json();


        // Evita usar a resposta de uma data anterior.
        if (consultaAtual !== consultaHorarios) return;


        if (!resposta.ok || resultado.sucesso !== true) {
            throw new Error(
                resultado.mensagem ||
                "Não foi possível consultar os horários."
            );
        }


        const ocupados = new Set(
            (resultado.horarios_ocupados || []).map(hora =>
                String(hora).substring(0, 5)
            )
        );


        campoHorario.replaceChildren();


        const primeiraOpcao = document.createElement("option");
        primeiraOpcao.value = "";
        primeiraOpcao.textContent = "Selecione o horário";
        campoHorario.appendChild(primeiraOpcao);


        let quantidadeDisponivel = 0;


        horariosPadrao.forEach(function (hora) {
            if (ocupados.has(hora)) {
                return;
            }


            const opcao = document.createElement("option");
            opcao.value = hora;
            opcao.textContent = hora;


            campoHorario.appendChild(opcao);
            quantidadeDisponivel++;
        });


        if (quantidadeDisponivel === 0) {
            primeiraOpcao.textContent =
                "Não há horários disponíveis nesta data";


            if (ajuda) {
                ajuda.textContent =
                    "Escolha outra data para consultar novos horários.";
            }
        } else if (ajuda) {
            ajuda.textContent =
                "São exibidos apenas horários disponíveis para este monitor.";
        }


        campoHorario.disabled = quantidadeDisponivel === 0;


    } catch (erro) {
        if (consultaAtual !== consultaHorarios) return;


        console.error("Erro ao consultar horários:", erro);


        campoHorario.replaceChildren();


        const opcaoErro = document.createElement("option");
        opcaoErro.value = "";
        opcaoErro.textContent = "Erro ao consultar horários";


        campoHorario.appendChild(opcaoErro);
        campoHorario.disabled = true;


        if (ajuda) {
            ajuda.textContent =
                "Não foi possível consultar. Tente novamente.";
        }


        mostrarMensagem(
            "Não foi possível verificar os horários. Confira sua conexão e tente novamente."
        );
    }
}


/* =====================================================
   ENVIAR AGENDAMENTO
===================================================== */


async function enviarAgendamento(evento) {
    evento.preventDefault();


    const botao = elemento("botaoAgendar");
    const formulario = elemento("formAgendamento");


    if (botao.disabled) return;


    if (!idMonitorSelecionado) {
        mostrarMensagem(
            "Monitor não identificado. Volte à disciplina e selecione um monitor."
        );
        return;
    }


    const matricula = localStorage.getItem("matricula");


    const campoData = elemento("data");
    const campoHorario = elemento("horario");
    const campoAssunto = elemento("assunto");
    const campoObservacao = elemento("observacao");


    const data = campoData.value;
    const horario = campoHorario.value;
    const assunto = campoAssunto.value.trim();
    const observacao = campoObservacao.value.trim();


    if (!matricula) {
        mostrarMensagem(
            "Não foi possível identificar o aluno. Entre novamente no sistema."
        );
        return;
    }


    if (!data || !horario || !assunto) {
        mostrarMensagem(
            "Preencha a data, o horário e o assunto da monitoria."
        );
        return;
    }


    if (campoData.min && data < campoData.min) {
        mostrarMensagem(
            "A data da monitoria não pode ser anterior a hoje."
        );
        return;
    }


    const dados = {
        matricula: matricula,
        id_monitor: idMonitorSelecionado,
        data_agendamento: data,
        hora_inicio: horario,
        assunto: assunto,
        observacao: observacao
    };


    try {
        botao.disabled = true;
        botao.textContent = "Agendando...";


        mostrarMensagem("Verificando e salvando o agendamento...", true);


        const resposta = await fetch("./api/agendar.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(dados),
            cache: "no-store"
        });


        const textoResposta = await resposta.text();


        let resultado;


        try {
            resultado = JSON.parse(textoResposta);
        } catch {
            console.error("Resposta recebida:", textoResposta);


            throw new Error(
                "O servidor retornou uma resposta inválida. Confira o PHP."
            );
        }


        if (!resposta.ok || resultado.sucesso !== true) {
            throw new Error(
                resultado.mensagem ||
                "Não foi possível realizar o agendamento."
            );
        }


        elemento("assunto").value = "";
elemento("observacao").value = "";
elemento("horario").value = "";


mostrarMensagem(
    resultado.mensagem || "Monitoria agendada com sucesso!",
    true
);


await carregarHorariosDisponiveis();
    } catch (erro) {
        console.error("Erro ao agendar:", erro);


        mostrarMensagem(
            erro.message || "Erro ao realizar agendamento."
        );
    } finally {
        botao.disabled = false;
        botao.textContent = "AGENDAR MONITORIA";
    }
}


/* =====================================================
   INICIALIZAÇÃO
===================================================== */


document.addEventListener("DOMContentLoaded", function () {
    carregarMonitorSelecionado();
    carregarPerfilAluno();
    configurarDataMinima();


    const formulario = elemento("formAgendamento");
    const campoData = elemento("data");


    if (campoData) {
        campoData.addEventListener(
            "change",
            carregarHorariosDisponiveis
        );
    }


    if (formulario) {
        formulario.addEventListener(
            "submit",
            enviarAgendamento
        );
    }


    console.log("SACM: agendamento inicializado.");
});
