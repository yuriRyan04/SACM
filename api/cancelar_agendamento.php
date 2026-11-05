
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . "/conexao.php";

/* =====================================================
   RESPOSTA JSON
===================================================== */

function responder($sucesso, $mensagem, $statusHTTP = 200)
{
    http_response_code($statusHTTP);

    echo json_encode([
        "sucesso" => $sucesso,
        "mensagem" => $mensagem
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/* =====================================================
   VALIDAR MÉTODO
===================================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Método não permitido.", 405);
}

/* =====================================================
   VALIDAR CONEXÃO
===================================================== */

if (!isset($mysqli) || $mysqli->connect_error) {
    responder(false, "Erro na conexão com o banco.", 500);
}

/* =====================================================
   IDENTIFICAR ALUNO
===================================================== */

/*
 * Prioriza a matrícula da sessão autenticada.
 * O POST é compatibilidade com o login atual do SACM.
 */

if (isset($_SESSION["matricula"]) && $_SESSION["matricula"] !== "") {

    if (($_SESSION["tipo"] ?? "") !== "aluno") {
        responder(false, "Apenas alunos podem cancelar seus agendamentos.", 403);
    }

    $matricula = trim((string) $_SESSION["matricula"]);

} else {
    $matricula = trim((string) ($_POST["matricula"] ?? ""));
}

$idAgendamento = filter_var(
    $_POST["id_agendamento"] ?? null,
    FILTER_VALIDATE_INT
);

if ($matricula === "" || !$idAgendamento || $idAgendamento < 1) {
    responder(false, "Dados do cancelamento inválidos.", 400);
}

/* =====================================================
   BUSCAR O ALUNO
===================================================== */

$stmtAluno = $mysqli->prepare("
    SELECT id_aluno
    FROM aluno
    WHERE matricula = ?
    LIMIT 1
");

if (!$stmtAluno) {
    error_log("SACM cancelar — prepare aluno: " . $mysqli->error);
    responder(false, "Erro ao identificar o aluno.", 500);
}

$stmtAluno->bind_param("s", $matricula);

if (!$stmtAluno->execute()) {
    error_log("SACM cancelar — execute aluno: " . $stmtAluno->error);
    $stmtAluno->close();

    responder(false, "Erro ao consultar o aluno.", 500);
}

$resultadoAluno = $stmtAluno->get_result();
$aluno = $resultadoAluno->fetch_assoc();

$stmtAluno->close();

if (!$aluno) {
    responder(false, "Aluno não encontrado.", 404);
}

$idAluno = (int) $aluno["id_aluno"];

/* =====================================================
   VERIFICAR PROPRIEDADE E STATUS
===================================================== */

$stmtBusca = $mysqli->prepare("
    SELECT status
    FROM agendamentos
    WHERE id_agendamento = ?
      AND id_aluno = ?
    LIMIT 1
");

if (!$stmtBusca) {
    error_log("SACM cancelar — prepare agendamento: " . $mysqli->error);
    responder(false, "Erro ao consultar o agendamento.", 500);
}

$stmtBusca->bind_param("ii", $idAgendamento, $idAluno);

if (!$stmtBusca->execute()) {
    error_log("SACM cancelar — execute agendamento: " . $stmtBusca->error);
    $stmtBusca->close();

    responder(false, "Erro ao verificar o agendamento.", 500);
}

$resultadoBusca = $stmtBusca->get_result();
$agendamento = $resultadoBusca->fetch_assoc();

$stmtBusca->close();

if (!$agendamento) {
    responder(
        false,
        "Agendamento não encontrado ou não pertence a você.",
        404
    );
}

$statusAtual = mb_strtolower(
    trim((string) $agendamento["status"]),
    "UTF-8"
);

$statusNormalizado = strtr($statusAtual, [
    "á" => "a",
    "à" => "a",
    "ã" => "a",
    "â" => "a",
    "é" => "e",
    "ê" => "e",
    "í" => "i",
    "ó" => "o",
    "ô" => "o",
    "õ" => "o",
    "ú" => "u"
]);

if ($statusNormalizado !== "agendado") {
    responder(
        false,
        "Este agendamento não pode ser cancelado porque não está mais agendado.",
        409
    );
}

/* =====================================================
   ATUALIZAR STATUS
===================================================== */

$stmtCancelar = $mysqli->prepare("
    UPDATE agendamentos
    SET status = 'cancelado'
    WHERE id_agendamento = ?
      AND id_aluno = ?
      AND LOWER(TRIM(status)) = 'agendado'
");

if (!$stmtCancelar) {
    error_log("SACM cancelar — prepare update: " . $mysqli->error);
    responder(false, "Erro ao preparar o cancelamento.", 500);
}

$stmtCancelar->bind_param("ii", $idAgendamento, $idAluno);

if (!$stmtCancelar->execute()) {
    error_log("SACM cancelar — update: " . $stmtCancelar->error);
    $stmtCancelar->close();

    responder(false, "Não foi possível cancelar o agendamento.", 500);
}

$alterados = $stmtCancelar->affected_rows;

$stmtCancelar->close();

if ($alterados !== 1) {
    responder(
        false,
        "O agendamento foi alterado ou cancelado anteriormente. Atualize a página.",
        409
    );
}

responder(true, "Agendamento cancelado com sucesso.");

?>
