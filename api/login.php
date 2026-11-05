
<?php

/*
==========================================================
SACM — LOGIN COMPLETO

ALUNO: LDAP + SINCRONIZAÇÃO MYSQL
PROFESSOR: BANCO LOCAL MYSQL
GESTÃO: BANCO LOCAL MYSQL
==========================================================
*/

error_reporting(0);
ini_set("display_errors", "0");

ob_start();
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
==========================================================
FUNÇÃO DE RESPOSTA JSON
==========================================================
*/

function responder($sucesso, $codigo, $dados = [])
{
    if (ob_get_level() > 0) {
        ob_clean();
    }

    http_response_code(200);

    echo json_encode(
        array_merge([
            "sucesso" => $sucesso,
            "codigo" => $codigo
        ], $dados),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/*
==========================================================
VALIDAR SENHA DO BANCO LOCAL
==========================================================
*/

function validarSenhaLocal($senhaDigitada, $senhaBanco)
{
    if (!is_string($senhaBanco) || $senhaBanco === "") {
        return false;
    }

    $informacoesHash = password_get_info($senhaBanco);

    if (($informacoesHash["algo"] ?? 0) !== 0) {
        return password_verify($senhaDigitada, $senhaBanco);
    }

    return hash_equals($senhaBanco, $senhaDigitada);
}

/*
==========================================================
CRIAR SESSÃO
==========================================================
*/

function iniciarSessao($tipo, $id, $matricula, $nome, $extras = [])
{
    session_regenerate_id(true);

    $_SESSION["tipo"] = $tipo;
    $_SESSION["id"] = $id;
    $_SESSION["matricula"] = $matricula;
    $_SESSION["nome"] = $nome;

    foreach ($extras as $chave => $valor) {
        $_SESSION[$chave] = $valor;
    }
}

/*
==========================================================
PROCESSAR LOGIN
==========================================================
*/

try {

    require_once __DIR__ . "/conexao.php";

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new RuntimeException(
            "A conexão MySQL não foi disponibilizada."
        );
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responder(false, "metodo_invalido");
    }

    $corpo = file_get_contents("php://input");
    $entrada = json_decode($corpo, true);

    if (!is_array($entrada)) {
        responder(false, "dados_invalidos");
    }

    $matricula = trim(
        (string)($entrada["matricula"] ?? "")
    );

    $senha = (string)($entrada["senha"] ?? "");

    $tipo = strtolower(
        trim((string)(
            $entrada["tipo"] ??
            $entrada["perfil"] ??
            ""
        ))
    );

    if ($matricula === "" || $senha === "") {
        responder(false, "campos_obrigatorios");
    }

    if (!in_array($tipo, ["aluno", "professor", "gestao"], true)) {
        responder(false, "perfil_invalido");
    }

    /*
    ======================================================
    1. ALUNO — AUTENTICAÇÃO LDAP
    SINCRONIZAÇÃO COM O BANCO LOCAL
    ======================================================
    */

    if ($tipo === "aluno") {

        if (
            !function_exists("ldap_connect") ||
            !function_exists("ldap_escape")
        ) {
            responder(false, "ldap_indisponivel");
        }

        // Carrega as configurações privadas do LDAP.
        $ldapConfig = require __DIR__ . "/ldap_config.php";

        if (
            !is_array($ldapConfig) ||
            empty($ldapConfig["servidor"]) ||
            empty($ldapConfig["sufixo"]) ||
            empty($ldapConfig["base_dn"]) ||
            empty($ldapConfig["padrao_grupo"])
        ) {
            responder(false, "ldap_indisponivel");
        }

        $ldap = @ldap_connect($ldapConfig["servidor"]);

        if (!$ldap) {
            responder(false, "ldap_indisponivel");
        }

        ldap_set_option(
            $ldap,
            LDAP_OPT_PROTOCOL_VERSION,
            3
        );

        ldap_set_option(
            $ldap,
            LDAP_OPT_REFERRALS,
            0
        );

        ldap_set_option(
            $ldap,
            LDAP_OPT_NETWORK_TIMEOUT,
            5
        );

        $usuarioLDAP = $matricula . $ldapConfig["sufixo"];

        // Valida a matrícula e a senha no LDAP.
        if (!@ldap_bind($ldap, $usuarioLDAP, $senha)) {
            @ldap_unbind($ldap);
            responder(false, "login_invalido");
        }

        $baseDN = $ldapConfig["base_dn"];

        $filtro = "(sAMAccountName=" .
            ldap_escape(
                $matricula,
                "",
                LDAP_ESCAPE_FILTER
            ) .
            ")";

        $pesquisa = @ldap_search(
            $ldap,
            $baseDN,
            $filtro,
            ["displayname", "memberof"]
        );

        if (!$pesquisa) {
            @ldap_unbind($ldap);
            responder(false, "erro_pesquisa_ldap");
        }

        $resultadoLDAP = ldap_get_entries(
            $ldap,
            $pesquisa
        );

        if (
            !isset($resultadoLDAP["count"]) ||
            $resultadoLDAP["count"] < 1
        ) {
            @ldap_unbind($ldap);
            responder(false, "aluno_nao_encontrado_ldap");
        }

        $nomeLDAP = trim(
            (string)(
                $resultadoLDAP[0]["displayname"][0] ?? ""
            )
        );

        $grupos = $resultadoLDAP[0]["memberof"] ?? [
            "count" => 0
        ];

        $serie = "";
        $turma = "";
        $curso = "";

        // Identifica série, turma e curso pelo grupo LDAP.
        for (
            $i = 0;
            $i < (int)($grupos["count"] ?? 0);
            $i++
        ) {

            $nomeGrupo = (string)($grupos[$i] ?? "");

            if (
                preg_match(
                    $ldapConfig["padrao_grupo"],
                    $nomeGrupo,
                    $matches
                )
            ) {

                $serie = $matches[1] . "º";
                $turma = strtoupper($matches[2]);
                $curso = ucfirst(strtolower($matches[3]));

                break;
            }
        }

        @ldap_unbind($ldap);

        if ($nomeLDAP === "") {
            responder(false, "dados_ldap_incompletos");
        }

        // Procura o aluno no banco local.
        $stmt = $mysqli->prepare(
            "SELECT id_aluno, Nome, matricula,
                    serie, turma, curso, foto
             FROM aluno
             WHERE matricula = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $matricula);
        $stmt->execute();

        $aluno = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$aluno) {

            // Novo aluno: cadastra os dados sincronizados.
            $stmt = $mysqli->prepare(
                "INSERT INTO aluno
                    (Nome, matricula, serie, turma, curso)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssss",
                $nomeLDAP,
                $matricula,
                $serie,
                $turma,
                $curso
            );

            $stmt->execute();
            $stmt->close();

        } else {

            // Aluno existente: atualiza os dados do LDAP.
            $stmt = $mysqli->prepare(
                "UPDATE aluno
                 SET Nome = ?, serie = ?, turma = ?, curso = ?
                 WHERE id_aluno = ?"
            );

            $stmt->bind_param(
                "ssssi",
                $nomeLDAP,
                $serie,
                $turma,
                $curso,
                $aluno["id_aluno"]
            );

            $stmt->execute();
            $stmt->close();
        }

        // Recupera os dados atualizados.
        $stmt = $mysqli->prepare(
            "SELECT id_aluno, Nome, matricula,
                    serie, turma, curso, foto
             FROM aluno
             WHERE matricula = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $matricula);
        $stmt->execute();

        $aluno = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$aluno) {
            responder(false, "erro_banco");
        }

        iniciarSessao(
            "aluno",
            $aluno["id_aluno"],
            $aluno["matricula"],
            $aluno["Nome"],
            [
                "id_aluno" => $aluno["id_aluno"],
                "serie" => $aluno["serie"] ?? null,
                "turma" => $aluno["turma"] ?? null,
                "curso" => $aluno["curso"] ?? null,
                "foto" => $aluno["foto"] ?? null
            ]
        );

        responder(true, "login_ok", [
            "tipo" => "aluno",
            "matricula" => $aluno["matricula"],
            "nome" => $aluno["Nome"],
            "serie" => $aluno["serie"] ?? null,
            "turma" => $aluno["turma"] ?? null,
            "curso" => $aluno["curso"] ?? null,
            "foto" => $aluno["foto"] ?? null
        ]);
    }

    /*
    ======================================================
    2. PROFESSOR — BANCO LOCAL SACM
    ======================================================
    */

    if ($tipo === "professor") {

        $stmt = $mysqli->prepare(
            "SELECT id_professor, nome, matricula,
                    senha, foto, id_disciplinas
             FROM professor
             WHERE matricula = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $matricula);
        $stmt->execute();

        $professor = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (
            !$professor ||
            !validarSenhaLocal(
                $senha,
                $professor["senha"] ?? ""
            )
        ) {
            responder(false, "login_invalido");
        }

        iniciarSessao(
            "professor",
            $professor["id_professor"],
            $professor["matricula"],
            $professor["nome"],
            [
                "id_professor" => $professor["id_professor"],
                "foto" => $professor["foto"] ?? null,
                "id_disciplinas" =>
                    $professor["id_disciplinas"] ?? null
            ]
        );

        responder(true, "login_ok", [
            "tipo" => "professor",
            "matricula" => $professor["matricula"],
            "nome" => $professor["nome"],
            "foto" => $professor["foto"] ?? null,
            "id_disciplinas" =>
                $professor["id_disciplinas"] ?? null
        ]);
    }

    /*
    ======================================================
    3. GESTÃO — BANCO LOCAL SACM
    ======================================================
    */

    if ($tipo === "gestao") {

        $stmt = $mysqli->prepare(
            "SELECT id_gestao, nome, matricula, senha, foto
             FROM gestao
             WHERE matricula = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $matricula);
        $stmt->execute();

        $gestao = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (
            !$gestao ||
            !validarSenhaLocal(
                $senha,
                $gestao["senha"] ?? ""
            )
        ) {
            responder(false, "login_invalido");
        }

        iniciarSessao(
            "gestao",
            $gestao["id_gestao"],
            $gestao["matricula"],
            $gestao["nome"],
            [
                "id_gestao" => $gestao["id_gestao"],
                "foto" => $gestao["foto"] ?? null
            ]
        );

        responder(true, "login_ok", [
            "tipo" => "gestao",
            "matricula" => $gestao["matricula"],
            "nome" => $gestao["nome"],
            "foto" => $gestao["foto"] ?? null
        ]);
    }

    responder(false, "perfil_invalido");

} catch (Throwable $e) {

    // O detalhe técnico fica no log do servidor.
    error_log(
        "Erro no login SACM: " . $e->getMessage()
    );

    responder(false, "erro_banco");
}

?>
