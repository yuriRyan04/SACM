<?php

/* ==========================================================
   SACM - HORÁRIOS INDISPONÍVEIS
========================================================== */

ini_set("display_errors", "0");
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

mysqli_report(MYSQLI_REPORT_OFF);

function responder($sucesso, $mensagem = "", $dados = [])
{
    echo json_encode(
        array_merge([
            "sucesso" => $sucesso,
            "mensagem" => $mensagem
        ], $dados),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

/* Conexão */

require_once __DIR__ . "/conexao.php";

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    responder(false, "Erro na conexão com o banco de dados.");
}

$mysqli->set_charset("utf8mb4");

/* Receber parâmetros */

$idMonitor = filter_input(
    INPUT_GET,
    "id_monitor",
    FILTER_VALIDATE_INT
);

$data = trim($_GET["data"] ?? "");

$dataObjeto = DateTime::createFromFormat("!Y-m-d", $data);

if (
    !$idMonitor ||
    $idMonitor < 1 ||
    !$dataObjeto ||
    $dataObjeto->format("Y-m-d") !== $data
) {
    http_response_code(400);

    responder(
        false,
        "Informe um monitor e uma data válidos."
    );
}

/* Confirmar que o monitor existe */

$sql = "
    SELECT id_monitor
    FROM monitores
    WHERE id_monitor = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    responder(false, "Erro ao consultar o monitor.");
}

$stmt->bind_param("i", $idMonitor);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);
    responder(false, "Não foi possível verificar o monitor.");
}

$resultado = $stmt->get_result();

if (!$resultado || $resultado->num_rows === 0) {
    $stmt->close();

    http_response_code(404);
    responder(false, "Monitor não encontrado.");
}

$stmt->close();

/* Consultar horários ocupados */

$sql = "
    SELECT hora_inicio
    FROM agendamentos
    WHERE id_monitor = ?
      AND data_agendamento = ?
      AND (
          status IS NULL
          OR status = 'agendado'
      )
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    responder(false, "Erro ao consultar a disponibilidade.");
}

$stmt->bind_param("is", $idMonitor, $data);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);
    responder(false, "Falha ao consultar os horários.");
}

$resultado = $stmt->get_result();
$ocupados = [];

while ($linha = $resultado->fetch_assoc()) {
    $ocupados[] = substr($linha["hora_inicio"], 0, 5);
}

$stmt->close();

/* Resposta sem dados pessoais */

responder(true, "", [
    "horarios_ocupados" => array_values(
        array_unique($ocupados)
    )
]);

?>