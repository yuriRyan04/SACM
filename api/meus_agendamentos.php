<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . "/conexao.php";

/* =========================================
   RESPOSTA JSON
========================================= */

function responder($sucesso, $mensagem, $agendamentos = [], $status = 200)
{
    http_response_code($status);

    echo json_encode([
        "sucesso" => $sucesso,
        "mensagem" => $mensagem,
        "agendamentos" => $agendamentos
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/* =========================================
   CONEXÃO
========================================= */

if (!isset($mysqli) || $mysqli->connect_error) {
    responder(
        false,
        "Erro na conexão com o banco de dados.",
        [],
        500
    );
}

/* =========================================
   IDENTIFICAR ALUNO
========================================= */

if (
    isset($_SESSION["matricula"]) &&
    ($_SESSION["tipo"] ?? "") === "aluno"
) {
    $matricula = trim((string) $_SESSION["matricula"]);
} else {
    // Compatibilidade com o login atual do SACM.
    $matricula = trim((string) ($_GET["matricula"] ?? ""));
}

if ($matricula === "") {
    responder(false, "Matrícula não informada.", [], 400);
}

/* =========================================
   BUSCAR ALUNO
   A chave correta é id_aluno, não id.
========================================= */

$sqlAluno = "
    SELECT id_aluno
    FROM aluno
    WHERE matricula = ?
    LIMIT 1
";

$stmtAluno = $mysqli->prepare($sqlAluno);

if (!$stmtAluno) {
    error_log("Erro ao preparar busca de aluno: " . $mysqli->error);

    responder(
        false,
        "Erro ao preparar consulta do aluno.",
        [],
        500
    );
}

$stmtAluno->bind_param("s", $matricula);

if (!$stmtAluno->execute()) {
    error_log("Erro ao executar busca de aluno: " . $stmtAluno->error);

    $stmtAluno->close();

    responder(false, "Erro ao consultar o aluno.", [], 500);
}

$resultadoAluno = $stmtAluno->get_result();
$aluno = $resultadoAluno->fetch_assoc();

$stmtAluno->close();

if (!$aluno) {
    responder(false, "Aluno não encontrado.", [], 404);
}

$idAluno = (int) $aluno["id_aluno"];

/* =========================================
   BUSCAR AGENDAMENTOS
   Tabela: agendamentos
========================================= */

$sql = "
    SELECT
        ag.id_agendamento,
        ag.id_aluno,
        ag.id_monitor,
        ag.id_disciplinas,
        ag.data_agendamento,
        ag.hora_inicio,
        ag.assunto,
        ag.observacao,
        ag.status,

        COALESCE(
            d.disciplina,
            'Disciplina não identificada'
        ) AS disciplina,

        COALESCE(
            mon.Nome,
            'Monitor não identificado'
        ) AS monitor

    FROM agendamentos AS ag

    LEFT JOIN disciplinas AS d
        ON d.id_disciplinas = ag.id_disciplinas

    LEFT JOIN monitores AS m
        ON m.id_monitor = ag.id_monitor

    LEFT JOIN aluno AS mon
        ON mon.id_aluno = m.id_aluno

    WHERE ag.id_aluno = ?

    ORDER BY
        ag.data_agendamento DESC,
        ag.hora_inicio DESC
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log(
        "Erro ao preparar consulta de agendamentos: " .
        $mysqli->error
    );

    responder(
        false,
        "Erro ao preparar consulta dos agendamentos. Verifique a estrutura do banco.",
        [],
        500
    );
}

$stmt->bind_param("i", $idAluno);

if (!$stmt->execute()) {
    error_log(
        "Erro ao executar consulta de agendamentos: " .
        $stmt->error
    );

    $stmt->close();

    responder(
        false,
        "Erro ao consultar os agendamentos.",
        [],
        500
    );
}

/* =========================================
   ORGANIZAR RESULTADOS
========================================= */

$resultado = $stmt->get_result();
$agendamentos = [];

while ($linha = $resultado->fetch_assoc()) {
    $agendamentos[] = $linha;
}

$stmt->close();

/* =========================================
   RETORNAR JSON
========================================= */

responder(
    true,
    "Agendamentos carregados com sucesso.",
    $agendamentos
);

?>