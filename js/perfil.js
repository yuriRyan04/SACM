
"use strict";

/* =========================================
   SACM — PERFIL DO USUÁRIO
   Compatível com as páginas do sistema
   Não altera o menu.js
========================================= */

document.addEventListener("DOMContentLoaded", function () {
    carregarPerfil();
    configurarUploadFoto();
});

/* =========================================
   FUNÇÕES AUXILIARES
========================================= */

function elemento(id) {
    return document.getElementById(id);
}

function definirTexto(id, valor) {
    const campo = elemento(id);

    if (campo) {
        campo.textContent = valor ?? "";
    }
}

function definirImagem(id, caminho) {
    const imagem = elemento(id);

    if (imagem && caminho) {
        imagem.src = caminho;
    }
}

/* =========================================
   CARREGAR PERFIL DO USUÁRIO
========================================= */

async function carregarPerfil() {
    const matricula = localStorage.getItem("matricula");

    if (!matricula) {
        definirTexto("nomePerfil", "Usuário não identificado");
        definirTexto("tipoPerfil", "");

        console.warn(
            "SACM: matrícula não encontrada no localStorage."
        );

        return;
    }

    try {
        // CORREÇÃO: utiliza alunos.php, que já existe.
        const url = "./api/alunos.php";

        const resposta = await fetch(url, {
            method: "GET",
            headers: {
                "Accept": "application/json"
            },
            cache: "no-store",
            credentials: "same-origin"
        });

        if (!resposta.ok) {
            throw new Error(
                "Erro HTTP " + resposta.status +
                " ao consultar api/alunos.php."
            );
        }

        const texto = await resposta.text();

        let resultado;

        try {
            resultado = JSON.parse(texto);
        } catch {
            console.error(
                "Resposta recebida de api/alunos.php:",
                texto
            );

            throw new Error(
                "A API não retornou JSON válido."
            );
        }

        const dados = normalizarDadosPerfil(resultado);

        if (!dados) {
            console.error("Dados recebidos:", resultado);

            throw new Error(
                resultado.codigo === "nao_logado"
                    ? "A sessão não foi encontrada. Entre novamente no SACM."
                    : "A API não retornou dados válidos do usuário."
            );
        }

        preencherPerfil(dados);

    } catch (erro) {
        console.error("Erro ao carregar perfil:", erro);
    }
}

/* =========================================
   NORMALIZAR DADOS RETORNADOS PELA API
========================================= */

function normalizarDadosPerfil(resultado) {
    if (!resultado || typeof resultado !== "object") {
        return null;
    }

    if (
        resultado.sucesso === false ||
        resultado.erro
    ) {
        return null;
    }

    let dados = resultado;

    if (!Array.isArray(dados)) {
        dados =
            dados.aluno ??
            dados.usuario ??
            dados.dados ??
            dados.data ??
            dados;
    }

    if (Array.isArray(dados)) {
        dados = dados.length > 0 ? dados[0] : null;
    }

    if (!dados || typeof dados !== "object") {
        return null;
    }

    const possuiDados =
        dados.nome !== undefined ||
        dados.Nome !== undefined ||
        dados.nomeUsuario !== undefined ||
        dados.nome_completo !== undefined ||
        dados.matricula !== undefined ||
        dados.foto !== undefined ||
        dados.tipo !== undefined ||
        dados.perfil !== undefined;

    return possuiDados ? dados : null;
}

/* =========================================
   OBTER O PRIMEIRO VALOR DISPONÍVEL
========================================= */

function obterValor(dados, campos) {
    for (const campo of campos) {
        const valor = dados[campo];

        if (
            valor !== undefined &&
            valor !== null &&
            String(valor).trim() !== ""
        ) {
            return String(valor).trim();
        }
    }

    return "";
}

/* =========================================
   PREENCHER PERFIL
========================================= */

function preencherPerfil(dados) {
    const nome = obterValor(dados, [
        "nome",
        "Nome",
        "nomeUsuario",
        "nome_completo",
        "nome_completo_aluno"
    ]) || "Usuário";

    const matricula = obterValor(dados, [
        "matricula",
        "matrícula"
    ]) || localStorage.getItem("matricula") || "";

    /* Série */
    const serie = obterValor(dados, [
        "serie",
        "série",
        "serie_aluno",
        "serie_escolar",
        "ano_serie"
    ]);

    /* Turma */
    const turma = obterValor(dados, [
        "turma",
        "turma_aluno",
        "nome_turma"
    ]);

    /* Curso */
    const curso = obterValor(dados, [
        "curso",
        "nome_curso",
        "curso_aluno"
    ]);

    /*
       Junta série, turma e curso sem separadores.
    */
    let turmaCompleta = [
        serie,
        turma,
        curso
    ]
        .filter(valor => valor !== "")
        .join(" ");

    if (!turmaCompleta) {
        turmaCompleta = obterValor(dados, [
            "serie_turma_curso",
            "turma_completa",
            "identificacao_turma"
        ]);
    }

    const tipo = obterValor(dados, [
        "tipo",
        "perfil",
        "tipo_usuario",
        "cargo"
    ]) || "Aluno";

    const foto = obterValor(dados, [
        "foto",
        "foto_perfil",
        "imagem"
    ]);

    /* Perfil no menu lateral */
    definirTexto("nomePerfil", nome);
    definirTexto("tipoPerfil", tipo);

    if (foto) {
        definirImagem("fotoPerfil", foto);
    }

    /* Dados completos do usuário */
    definirTexto("nomeUsuario", nome);
    definirTexto("matriculaUsuario", matricula);

    if (foto) {
        definirImagem("fotoUsuario", foto);
    }

    /* Mantém a matrícula salva */
    if (matricula) {
        localStorage.setItem("matricula", matricula);
    }

    /* Normaliza o tipo de usuário */
    const tipoNormalizado = tipo
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");

    const ehAluno = tipoNormalizado === "aluno";

    const campoTurma = elemento("turmaUsuario");
    const linhaTurma = elemento("linhaTurma");

    /* Professor e gestão não exibem turma */
    if (!ehAluno) {
        if (campoTurma) {
            campoTurma.textContent = "";
        }

        if (linhaTurma) {
            linhaTurma.style.display = "none";
        }
    } else {
        definirTexto("turmaUsuario", turmaCompleta);

        if (linhaTurma) {
            linhaTurma.style.display = "";
        }
    }
}

/* =========================================
   ENVIO E ATUALIZAÇÃO DA FOTO
========================================= */

function configurarUploadFoto() {
    const inputFoto = elemento("inputFoto");

    if (!inputFoto) {
        return;
    }

    if (inputFoto.dataset.perfilConfigurado === "true") {
        return;
    }

    inputFoto.dataset.perfilConfigurado = "true";

    inputFoto.addEventListener("change", async function () {
        const arquivo = inputFoto.files?.[0];

        if (!arquivo) {
            return;
        }

        const matricula = localStorage.getItem("matricula");

        if (!matricula) {
            alert("Não foi possível identificar o usuário.");
            inputFoto.value = "";
            return;
        }

        const tiposPermitidos = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        if (!tiposPermitidos.includes(arquivo.type)) {
            alert("Selecione uma imagem JPG, PNG ou WEBP.");
            inputFoto.value = "";
            return;
        }

        if (arquivo.size > 5 * 1024 * 1024) {
            alert("A imagem deve ter no máximo 5 MB.");
            inputFoto.value = "";
            return;
        }

        const imagemPerfil = elemento("fotoPerfil");
        const imagemUsuario = elemento("fotoUsuario");

        const fotoAnterior =
            imagemPerfil?.getAttribute("src") ||
            imagemUsuario?.getAttribute("src") ||
            "";

        try {
            /* Exibe a prévia da imagem */
            const leitor = new FileReader();

            leitor.onload = function (evento) {
                definirImagem(
                    "fotoPerfil",
                    evento.target.result
                );

                definirImagem(
                    "fotoUsuario",
                    evento.target.result
                );
            };

            leitor.readAsDataURL(arquivo);

            /* Prepara o envio para o PHP */
            const formulario = new FormData();

            formulario.append("foto", arquivo);
            formulario.append("matricula", matricula);

            const resposta = await fetch(
                "./api/upload-foto.php",
                {
                    method: "POST",
                    body: formulario,
                    credentials: "same-origin"
                }
            );

            const texto = await resposta.text();

            let resultado;

            try {
                resultado = JSON.parse(texto);
            } catch {
                console.error("Resposta do upload:", texto);

                throw new Error(
                    "O servidor não retornou JSON válido ao salvar a foto."
                );
            }

            if (
                !resposta.ok ||
                resultado.sucesso === false ||
                resultado.erro
            ) {
                throw new Error(
                    resultado.mensagem ||
                    resultado.erro ||
                    "Não foi possível salvar a foto."
                );
            }

            /* Atualiza a foto com o caminho retornado */
            const caminhoFoto =
                resultado.foto ??
                resultado.caminho ??
                resultado.url ??
                "";

            if (caminhoFoto) {
                const caminhoAtualizado =
    caminhoFoto.split("?")[0] + "?v=" + Date.now();
    
                definirImagem(
                    "fotoPerfil",
                    caminhoAtualizado
                );

                definirImagem(
                    "fotoUsuario",
                    caminhoAtualizado
                );
            }

            alert(
                resultado.mensagem ||
                "Foto de perfil atualizada com sucesso!"
            );

        } catch (erro) {
            console.error(
                "Erro ao atualizar foto:",
                erro
            );

            /* Restaura a foto anterior se falhar */
            if (fotoAnterior) {
                definirImagem("fotoPerfil", fotoAnterior);
                definirImagem("fotoUsuario", fotoAnterior);
            }

            alert(
                erro.message ||
                "Não foi possível atualizar a foto."
            );

        } finally {
            inputFoto.value = "";
        }
    });
}
