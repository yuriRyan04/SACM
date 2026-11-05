
"use strict";

document.addEventListener("DOMContentLoaded", carregarPaginaProfessor);

async function carregarPaginaProfessor() {
    const mensagem = document.getElementById("mensagem");
    const lista = document.getElementById("listaDisciplinas");

    try {
        mensagem.textContent = "Carregando suas disciplinas...";
        mensagem.className = "mensagem";
        lista.replaceChildren();

        const resposta = await fetch("./api/disciplinas_professores.php", {
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Accept": "application/json" }
        });

        const texto = await resposta.text();
        let dados;

        try {
            dados = JSON.parse(texto);
        } catch {
            throw new Error("A API das disciplinas não retornou JSON válido.");
        }

        if (!resposta.ok || !dados.sucesso) {
            throw new Error(dados.mensagem || "Não foi possível carregar as disciplinas.");
        }

        preencherPerfil(dados.professor);

        const disciplinas = Array.isArray(dados.disciplinas)
            ? dados.disciplinas
            : [];

        document.getElementById("quantidadeDisciplinas").textContent =
            disciplinas.length;

        if (!disciplinas.length) {
            mensagem.textContent = "Nenhuma disciplina vinculada ao seu cadastro.";
            return;
        }

        mensagem.textContent = "";
        disciplinas.forEach(d => lista.appendChild(criarCartaoDisciplina(d)));

    } catch (erro) {
        console.error("Erro ao carregar professor:", erro);
        mensagem.textContent = erro.message || "Erro ao carregar as disciplinas.";
        mensagem.className = "mensagem erro";
    }
}

function preencherPerfil(professor) {
    if (!professor) {
        throw new Error("O servidor não retornou os dados do professor.");
    }

    const nome = professor.nome || "Professor";
    const matricula = professor.matricula || "Não informada";

    document.getElementById("nomeProfessor").textContent = nome;
    document.getElementById("nomeUsuario").textContent = nome;
    document.getElementById("matriculaProfessor").textContent = matricula;
    document.getElementById("matriculaUsuario").textContent = matricula;

    const foto = String(professor.foto || "").trim();

    if (foto && !/^\s*javascript:/i.test(foto)) {
        const imagem = document.getElementById("fotoProfessor");
        const imagemMenu = document.getElementById("fotoUsuario");

        imagem.src = foto;
        imagemMenu.src = foto;
    }

    document.getElementById("linhaTurma").hidden = true;
}

function criarCartaoDisciplina(disciplina) {
    const cartao = document.createElement("article");
    cartao.className = "card-disciplina";

    const icone = document.createElement("div");
    icone.className = "icone-disciplina";
    icone.textContent = "D";

    const titulo = document.createElement("h2");
    titulo.textContent = disciplina.nome || "Disciplina sem nome";

    const area = document.createElement("p");
    area.textContent = disciplina.area
        ? `Área: ${disciplina.area}`
        : "Disciplina cadastrada no SACM";

    const botao = document.createElement("a");
    botao.className = "botao-disciplina";
    botao.textContent = "Ver monitores";

    const id = Number(disciplina.id ?? disciplina.id_disciplinas);

    if (Number.isSafeInteger(id) && id > 0) {
        botao.href = `meus-monitores.html?id=${encodeURIComponent(id)}`;
    } else {
        botao.href = "#";
        botao.addEventListener("click", evento => {
            evento.preventDefault();
            console.error("Disciplina sem ID válido:", disciplina);
        });
    }

    cartao.append(icone, titulo, area, botao);
    return cartao;
}
