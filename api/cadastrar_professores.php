<?php

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/conexao.php";

function responder($sucesso, $mensagem, $dados = [], $status = 200)
{
    http_response_code($status);

    echo json_encode(
        array_merge([
            "sucesso" => $sucesso,
            "mensagem" => $mensagem
        ], $dados),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    responder(false, "Método não permitido.", [], 405);
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    responder(false, "Conexão com o banco indisponível.", [], 500);
}

$mysqli->set_charset("utf8mb4");

$entrada = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($entrada)) {
    responder(false, "Os dados enviados são inválidos.", [], 400);
}

// Receber os dados.
$nome = trim((string) ($entrada["nome"] ?? ""));
$matricula = trim((string) ($entrada["matricula"] ?? ""));
$cpf = trim((string) ($entrada["cpf"] ?? ""));

// Validar os campos.
if ($nome === "" || $matricula === "" || $cpf === "") {
    responder(false, "Preencha todos os campos obrigatórios.", [], 422);
}

$tamanhoNome = function_exists("mb_strlen")
    ? mb_strlen($nome, "UTF-8")
    : strlen($nome);

if ($tamanhoNome > 150) {
    responder(false, "O nome deve ter no máximo 150 caracteres.", [], 422);
}

if (!preg_match('/^[0-9]{1,30}$/', $matricula)) {
    responder(
        false,
        "A matrícula deve conter somente números.",
        [],
        422
    );
}

if (!preg_match('/^[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}$/', $cpf)) {
    responder(
        false,
        "Informe o CPF no formato 000.000.000-00.",
        [],
        422
    );
}

// Verificar matrícula duplicada.
$sql = "
    SELECT id_professor
    FROM professor
    WHERE matricula = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log("SACM - Verificação de matrícula: " . $mysqli->error);

    responder(
        false,
        "Erro ao verificar a matrícula no banco de dados.",
        [],
        500
    );
}

$stmt->bind_param("s", $matricula);

if (!$stmt->execute()) {
    error_log("SACM - Consulta de matrícula: " . $stmt->error);
    $stmt->close();

    responder(false, "Erro ao consultar a matrícula.", [], 500);
}

$resultado = $stmt->get_result();
$matriculaExiste = $resultado->num_rows > 0;

$stmt->close();

if ($matriculaExiste) {
    responder(
        false,
        "Já existe um professor com essa matrícula.",
        [],
        409
    );
}

// Verificar CPF duplicado.
$sql = "
    SELECT id_professor
    FROM professor
    WHERE cpf = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log("SACM - Verificação de CPF: " . $mysqli->error);

    responder(
        false,
        "Erro ao verificar o CPF no banco de dados.",
        [],
        500
    );
}

$stmt->bind_param("s", $cpf);

if (!$stmt->execute()) {
    error_log("SACM - Consulta de CPF: " . $stmt->error);
    $stmt->close();

    responder(false, "Erro ao consultar o CPF.", [], 500);
}

$resultado = $stmt->get_result();
$cpfExiste = $resultado->num_rows > 0;

$stmt->close();

if ($cpfExiste) {
    responder(
        false,
        "Já existe um professor cadastrado com esse CPF.",
        [],
        409
    );
}

/*
 * Gerar a senha inicial.
 * Esta versão mantém a regra de senha usada no código enviado.
 */
$senha = "ceep@" . $cpf;

// Inserir professor.
// A coluna id_disciplinas não é incluída.
$sql = "
    INSERT INTO professor
        (nome, matricula, senha, cpf)
    VALUES (?, ?, ?, ?)
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log("SACM - Preparação do cadastro: " . $mysqli->error);

    responder(
        false,
        "Não foi possível preparar o cadastro. Confira a tabela professor.",
        [],
        500
    );
}

$stmt->bind_param(
    "ssss",
    $nome,
    $matricula,
    $senha,
    $cpf
);

if (!$stmt->execute()) {
    $codigoErro = $stmt->errno;

    error_log(
        "SACM - Erro ao cadastrar professor: " . $stmt->error
    );

    $stmt->close();

    if ($codigoErro === 1062) {
        responder(
            false,
            "A matrícula ou o CPF já está cadastrado.",
            [],
            409
        );
    }

    responder(
        false,
        "Não foi possível cadastrar o professor. Verifique a estrutura da tabela.",
        [],
        500
    );
}

$idProfessor = $stmt->insert_id;

$stmt->close();

responder(
    true,
    "Professor cadastrado com sucesso! Você será redirecionado para o login.",
    [
        "id_professor" => $idProfessor
    ],
    201
);

?>