"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const $ = id => document.getElementById(id);

    const form = $("formCadastroProfessor");
    const nome = $("nome");
    const matricula = $("matricula");
    const cpf = $("cpf");
    const btnCadastrar = $("btnCadastrar");
    const btnLimpar = $("btnLimpar");
    const mensagem = $("mensagem");

    if (
        !form ||
        !nome ||
        !matricula ||
        !cpf ||
        !btnCadastrar ||
        !mensagem
    ) {
        console.error(
            "SACM: confira os IDs do formulário no HTML."
        );
        return;
    }

    function mostrarMensagem(texto, tipo = "erro") {
        mensagem.textContent = texto;
        mensagem.className = "mensagem";

        if (texto) {
            mensagem.classList.add(tipo);
        }
    }

    // Formatar CPF: 000.000.000-00.
    function formatarCPF(valor) {
        const numeros = valor
            .replace(/\D/g, "")
            .slice(0, 11);

        if (numeros.length > 9) {
            return (
                numeros.slice(0, 3) + "." +
                numeros.slice(3, 6) + "." +
                numeros.slice(6, 9) + "-" +
                numeros.slice(9, 11)
            );
        }

        if (numeros.length > 6) {
            return (
                numeros.slice(0, 3) + "." +
                numeros.slice(3, 6) + "." +
                numeros.slice(6)
            );
        }

        if (numeros.length > 3) {
            return (
                numeros.slice(0, 3) + "." +
                numeros.slice(3)
            );
        }

        return numeros;
    }

    cpf.addEventListener("input", () => {
        cpf.value = formatarCPF(cpf.value);
    });

    matricula.addEventListener("input", () => {
        matricula.value = matricula.value.replace(/\D/g, "");
    });

    async function requisicaoJSON(url, opcoes = {}) {
        let resposta;

        try {
            resposta = await fetch(url, {
                cache: "no-store",
                ...opcoes
            });
        } catch (erro) {
            console.error("Erro de conexão:", erro);

            throw new Error(
                "Não foi possível conectar ao servidor."
            );
        }

        const texto = await resposta.text();
        let dados;

        try {
            dados = JSON.parse(texto);
        } catch {
            console.error(
                "Resposta do servidor:",
                resposta.status,
                texto
            );

            throw new Error(
                resposta.status >= 500
                    ? "Erro interno do PHP. Consulte o log do servidor."
                    : "O servidor não retornou JSON válido."
            );
        }

        if (
            !resposta.ok ||
            dados.sucesso === false ||
            dados.erro
        ) {
            throw new Error(
                dados.mensagem ||
                dados.erro ||
                `Erro HTTP ${resposta.status}.`
            );
        }

        return dados;
    }

    async function cadastrarProfessor(evento) {
        evento.preventDefault();

        mostrarMensagem("");

        const nomeValor = nome.value.trim();
        const matriculaValor = matricula.value.trim();
        const cpfValor = cpf.value.trim();

        // Validar campos obrigatórios.
        if (!nomeValor || !matriculaValor || !cpfValor) {
            mostrarMensagem(
                "Preencha todos os campos.",
                "erro"
            );
            return;
        }

        // Validar nome.
        if (nomeValor.length > 150) {
            mostrarMensagem(
                "O nome deve ter no máximo 150 caracteres.",
                "erro"
            );

            nome.focus();
            return;
        }

        // Validar matrícula.
        if (!/^\d{1,30}$/.test(matriculaValor)) {
            mostrarMensagem(
                "A matrícula deve conter somente números.",
                "erro"
            );

            matricula.focus();
            return;
        }

        // Validar formato do CPF.
        if (!/^\d{3}\.\d{3}\.\d{3}-\d{2}$/.test(cpfValor)) {
            mostrarMensagem(
                "Informe o CPF completo no formato 000.000.000-00.",
                "erro"
            );

            cpf.focus();
            return;
        }

        btnCadastrar.disabled = true;
        btnCadastrar.textContent = "Cadastrando...";

        try {
            const dados = await requisicaoJSON(
                "./api/cadastrar_professores.php",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/json; charset=UTF-8"
                    },

                    body: JSON.stringify({
                        nome: nomeValor,
                        matricula: matriculaValor,
                        cpf: cpfValor
                    })
                }
            );

            // Confirmar o sucesso antes de redirecionar.
            if (dados.sucesso === true) {
                mostrarMensagem(
                    dados.mensagem ||
                    "Professor cadastrado com sucesso!",
                    "sucesso"
                );

                btnCadastrar.textContent = "Cadastro realizado!";

                // Retornar para o login após 1,5 segundo.
                setTimeout(() => {
                    window.location.href = "./index.html";
                }, 1500);

                return;
            }

            throw new Error(
                dados.mensagem ||
                "Não foi possível concluir o cadastro."
            );

        } catch (erro) {
            console.error(
                "Erro ao cadastrar professor:",
                erro
            );

            mostrarMensagem(erro.message, "erro");

            btnCadastrar.disabled = false;
            btnCadastrar.textContent = "Cadastrar Professor";
        }
    }

    form.addEventListener(
        "submit",
        cadastrarProfessor
    );

    if (btnLimpar) {
        btnLimpar.addEventListener("click", () => {
            mostrarMensagem("");
        });
    }
});