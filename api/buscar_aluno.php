<?php

/*
|--------------------------------------------------------------------------
| SACM — BUSCAR E SINCRONIZAR ALUNO PELO LDAP
|--------------------------------------------------------------------------
| 1. Recebe a matrícula.
| 2. Consulta o MySQL.
| 3. Se não encontrar, consulta o LDAP.
| 4. Extrai nome, série, turma e curso.
| 5. Cadastra o aluno no MySQL.
| 6. Retorna os dados em JSON.
|--------------------------------------------------------------------------
*/

session_start();

error_reporting(0);
ini_set("display_errors", "0");

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once __DIR__ . "/conexao.php";

/*
|--------------------------------------------------------------------------
| RESPOSTA JSON
|--------------------------------------------------------------------------
*/

function responder($sucesso, $mensagem, $dados = [], $status = 200)
{
    http_response_code($status);

    echo json_encode(
        array_merge([
            "sucesso" => $sucesso,
            "mensagem" => $mensagem
        ], $dados),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDAR CONEXÃO MYSQL
|--------------------------------------------------------------------------
*/

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    responder(
        false,
        "Conexão com o banco de dados indisponível.",
        ["codigo" => "mysql_indisponivel"],
        500
    );
}

/*
|--------------------------------------------------------------------------
| VALIDAR MATRÍCULA
|--------------------------------------------------------------------------
*/

$matricula = trim((string)($_GET["matricula"] ?? ""));

if ($matricula === "") {
    responder(false, "Informe a matrícula do aluno.", [], 400);
}

if (!preg_match('/^[a-zA-Z0-9.\_-]{1,50}$/', $matricula)) {
    responder(false, "Matrícula inválida.", [], 400);
}

/*
|--------------------------------------------------------------------------
| BUSCAR ALUNO NO MYSQL
|--------------------------------------------------------------------------
*/

function buscarAlunoLocal($mysqli, $matricula)
{
    $sql = "
        SELECT
            id_aluno,
            Nome,
            matricula,
            serie,
            turma,
            curso,
            foto
        FROM aluno
        WHERE matricula = ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $matricula);

    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $resultado = $stmt->get_result();
    $aluno = $resultado ? $resultado->fetch_assoc() : null;

    $stmt->close();

    return $aluno ?: null;
}

/*
|--------------------------------------------------------------------------
| RETORNAR DADOS DO ALUNO
|--------------------------------------------------------------------------
*/

function retornarAluno($aluno, $mensagem)
{
    responder(true, $mensagem, [
        "aluno" => [
            "id_aluno" => (int)$aluno["id_aluno"],
            "matricula" => $aluno["matricula"],
            "nome" => $aluno["Nome"],
            "Nome" => $aluno["Nome"],
            "serie" => $aluno["serie"] ?? "",
            "turma" => $aluno["turma"] ?? "",
            "curso" => $aluno["curso"] ?? "",
            "foto" => $aluno["foto"] ?? null
        ]
    ]);
}

/*
|--------------------------------------------------------------------------
| 1. CONSULTAR MYSQL PRIMEIRO
|--------------------------------------------------------------------------
*/

$aluno = buscarAlunoLocal($mysqli, $matricula);

if ($aluno === false) {
    responder(
        false,
        "Erro ao consultar o banco de dados.",
        ["codigo" => "erro_consulta_mysql"],
        500
    );
}

if ($aluno !== null) {
    retornarAluno($aluno, "Aluno encontrado no SACM.");
}

/*
|--------------------------------------------------------------------------
| 2. VERIFICAR EXTENSÃO LDAP
|--------------------------------------------------------------------------
*/

if (
    !function_exists("ldap_connect") ||
    !function_exists("ldap_escape")
) {
    responder(
        false,
        "A extensão LDAP do PHP não está habilitada corretamente.",
        ["codigo" => "ldap_indisponivel"],
        503
    );
}

/*
|--------------------------------------------------------------------------
| 3. CARREGAR CONFIGURAÇÕES LDAP PRIVADAS
|--------------------------------------------------------------------------
*/

$arquivoConfigLDAP = __DIR__ . "/ldap_config.php";

if (!file_exists($arquivoConfigLDAP)) {
    error_log("[SACM LDAP] Arquivo de configuração não encontrado.");

    responder(
        false,
        "As configurações do LDAP não estão disponíveis.",
        ["codigo" => "ldap_configuracao_ausente"],
        500
    );
}

$configLDAP = require $arquivoConfigLDAP;

if (
    !is_array($configLDAP) ||
    empty($configLDAP["servidor"]) ||
    empty($configLDAP["porta"]) ||
    empty($configLDAP["base_dn"]) ||
    empty($configLDAP["bind_user"]) ||
    empty($configLDAP["bind_password"])
) {
    error_log("[SACM LDAP] Configuração incompleta.");

    responder(
        false,
        "As configurações do LDAP estão incompletas.",
        ["codigo" => "ldap_configuracao_invalida"],
        500
    );
}

$servidorLDAP = $configLDAP["servidor"];
$portaLDAP = (int)$configLDAP["porta"];
$baseDN = $configLDAP["base_dn"];
$bindUser = $configLDAP["bind_user"];
$bindPass = $configLDAP["bind_password"];

/*
|--------------------------------------------------------------------------
| 4. CONECTAR AO LDAP
|--------------------------------------------------------------------------
*/

$ldap = @ldap_connect($servidorLDAP, $portaLDAP);

if (!$ldap) {
    responder(
        false,
        "Não foi possível conectar ao servidor LDAP.",
        ["codigo" => "ldap_indisponivel"],
        503
    );
}

ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 5);

/*
|--------------------------------------------------------------------------
| 5. AUTENTICAR CONTA TÉCNICA
|--------------------------------------------------------------------------
*/

if (!@ldap_bind($ldap, $bindUser, $bindPass)) {
    $codigoLDAP = ldap_errno($ldap);

    error_log(
        "[SACM LDAP] Falha na autenticação. Código: " . $codigoLDAP
    );

    @ldap_close($ldap);

    responder(
        false,
        "Falha na autenticação da conta técnica do LDAP.",
        ["codigo" => "ldap_bind_falhou"],
        503
    );
}

/*
|--------------------------------------------------------------------------
| 6. PESQUISAR MATRÍCULA
|--------------------------------------------------------------------------
*/

$matriculaEscapada = ldap_escape(
    $matricula,
    "",
    LDAP_ESCAPE_FILTER
);

$filtro = "(|(sAMAccountName={$matriculaEscapada})(cn={$matriculaEscapada}))";

$atributos = [
    "sAMAccountName",
    "displayName",
    "cn",
    "memberOf"
];

$pesquisa = @ldap_search(
    $ldap,
    $baseDN,
    $filtro,
    $atributos
);

if (!$pesquisa) {
    $codigoLDAP = ldap_errno($ldap);

    error_log(
        "[SACM LDAP] Erro de pesquisa. Código: " . $codigoLDAP
    );

    @ldap_close($ldap);

    responder(
        false,
        "Não foi possível pesquisar no LDAP. Confira a configuração e as permissões da conta técnica.",
        [
            "codigo" => "erro_pesquisa_ldap",
            "codigo_ldap" => $codigoLDAP
        ],
        500
    );
}

$resultados = @ldap_get_entries($ldap, $pesquisa);

if (
    !$resultados ||
    !isset($resultados["count"]) ||
    (int)$resultados["count"] < 1
) {
    @ldap_close($ldap);

    responder(
        false,
        "Aluno não encontrado na unidade de alunos do LDAP.",
        ["codigo" => "aluno_nao_encontrado_ldap"],
        404
    );
}

/*
|--------------------------------------------------------------------------
| 7. EXTRAIR NOME, MATRÍCULA E GRUPOS
|--------------------------------------------------------------------------
*/

$registro = $resultados[0];

// Normaliza as chaves do array retornado pelo LDAP.
$registro = array_change_key_case($registro, CASE_LOWER);

$matriculaLDAP =
    $registro["samaccountname"][0] ?? $matricula;

$nome = trim(
    $registro["displayname"][0]
    ?? $registro["cn"][0]
    ?? ""
);

$grupos = $registro["memberof"] ?? ["count" => 0];

@ldap_close($ldap);

if ($nome === "") {
    responder(
        false,
        "O LDAP não retornou o nome do aluno.",
        ["codigo" => "nome_ausente"],
        422
    );
}

/*
|--------------------------------------------------------------------------
| 8. EXTRAIR SÉRIE, TURMA E CURSO DO memberOf
|--------------------------------------------------------------------------
| Exemplo de grupo:
| CN=2_A_redes,OU=Grupos,DC=ceep,DC=plg
|--------------------------------------------------------------------------
*/

$serie = "";
$turma = "";
$curso = "";

$totalGrupos = (int)($grupos["count"] ?? 0);

for ($i = 0; $i < $totalGrupos; $i++) {
    $nomeGrupo = (string)($grupos[$i] ?? "");

    if (
        preg_match(
            '/(?:^|,)CN=(\d+)[°º]?_?([A-Za-z0-9]+)_([A-Za-z0-9_-]+)/i',
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

if ($serie === "" || $turma === "" || $curso === "") {
    error_log(
        "[SACM LDAP] Os grupos memberOf não correspondem ao padrão esperado."
    );

    responder(
        false,
        "Aluno encontrado no LDAP, mas não foi possível identificar série, turma e curso. Confira o formato dos grupos memberOf.",
        ["codigo" => "grupo_sem_formato_esperado"],
        422
    );
}

/*
|--------------------------------------------------------------------------
| 9. INSERIR ALUNO NO MYSQL
|--------------------------------------------------------------------------
| id_aluno deve ser AUTO_INCREMENT.
| Nenhuma senha do aluno é armazenada.
|--------------------------------------------------------------------------
*/

$sql = "
    INSERT INTO aluno
        (Nome, matricula, serie, turma, curso)
    VALUES
        (?, ?, ?, ?, ?)
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    responder(
        false,
        "Erro ao preparar o cadastro do aluno no SACM.",
        ["codigo" => "erro_preparacao_mysql"],
        500
    );
}

$stmt->bind_param(
    "sssss",
    $nome,
    $matriculaLDAP,
    $serie,
    $turma,
    $curso
);

if (!$stmt->execute()) {
    $erroMySQL = $stmt->errno;

    $stmt->close();

    if ($erroMySQL === 1062) {
        $aluno = buscarAlunoLocal($mysqli, $matriculaLDAP);

        if (is_array($aluno)) {
            retornarAluno($aluno, "Aluno encontrado no SACM.");
        }
    }

    error_log(
        "[SACM] Erro ao cadastrar aluno. Código MySQL: " . $erroMySQL
    );

    responder(
        false,
        "Não foi possível cadastrar o aluno no SACM. Confira as colunas, os índices e as restrições da tabela aluno.",
        ["codigo" => "erro_cadastro_mysql"],
        500
    );
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| 10. RECUPERAR E RETORNAR ALUNO CADASTRADO
|--------------------------------------------------------------------------
*/

$aluno = buscarAlunoLocal($mysqli, $matriculaLDAP);

if (!is_array($aluno)) {
    responder(
        false,
        "O aluno foi cadastrado, mas não foi possível recuperar os dados.",
        ["codigo" => "aluno_nao_recuperado"],
        500
    );
}

/*
|--------------------------------------------------------------------------
| 11. RETORNAR JSON
|--------------------------------------------------------------------------
*/

retornarAluno(
    $aluno,
    "Aluno sincronizado com sucesso a partir do LDAP."
);

?>