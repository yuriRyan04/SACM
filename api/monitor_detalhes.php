
<?php

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . "/conexao.php";

function responder($sucesso, $dados = [], $status = 200)
{
    http_response_code($status);

    echo json_encode(
        array_merge(["sucesso" => (bool) $sucesso], $dados),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/* =====================================================
   CONEXÃO
   ===================================================== */

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    responder(false, [
        "mensagem" => "Erro na conexão com o banco de dados."
    ], 500);
}

if ($mysqli->connect_errno) {
    error_log("SACM monitor_detalhes: " . $mysqli->connect_error);

    responder(false, [
        "mensagem" => "Não foi possível conectar ao banco de dados."
    ], 500);
}

$mysqli->set_charset("utf8mb4");

/* =====================================================
   RECEBER ID DO MONITOR
   ===================================================== */

$idMonitor = filter_input(
    INPUT_GET,
    "id_monitor",
    FILTER_VALIDATE_INT
);

if (!$idMonitor || $idMonitor <= 0) {
    responder(false, [
        "mensagem" => "ID do monitor inválido."
    ], 400);
}

/* =====================================================
   CONSULTAR MONITOR, ALUNO E DISCIPLINA
   ===================================================== */

$sql = "
    SELECT
        m.id_monitor,
        m.matricula,
        m.id_disciplinas,
        m.id_aluno,
        a.Nome AS nome,
        a.serie,
        a.turma,
        a.curso,
        a.foto,
        d.disciplina,
        d.area
    FROM monitores AS m

    INNER JOIN disciplinas AS d
        ON CAST(d.id_disciplinas AS CHAR) =
           TRIM(m.id_disciplinas)

    INNER JOIN aluno AS a
        ON a.id_aluno = m.id_aluno

    WHERE m.id_monitor = ?

    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log(
        "SACM monitor_detalhes - erro SQL: " . $mysqli->error
    );

    responder(false, [
        "mensagem" => "Erro ao preparar a consulta do monitor."
    ], 500);
}

$stmt->bind_param("i", $idMonitor);

if (!$stmt->execute()) {
    error_log(
        "SACM monitor_detalhes - execução: " . $stmt->error
    );

    $stmt->close();

    responder(false, [
        "mensagem" => "Erro ao buscar os dados do monitor."
    ], 500);
}

$resultado = $stmt->get_result();
$monitor = $resultado->fetch_assoc();

$stmt->close();

/* =====================================================
   VERIFICAR SE O MONITOR EXISTE
   ===================================================== */

if (!$monitor) {
    responder(false, [
        "mensagem" => "Monitor não encontrado ou sem disciplina cadastrada."
    ], 404);
}

/* =====================================================
   PREPARAR CAMINHO DA FOTO
   ===================================================== */

$caminhoFoto = trim((string) ($monitor["foto"] ?? ""));

$foto = "";

if ($caminhoFoto !== "") {
    // O banco armazena, por exemplo: img/alunos/202430025319.jpg
    $foto = "./" . ltrim($caminhoFoto, "/");
}

/* =====================================================
   RETORNAR DADOS PARA O JAVASCRIPT
   ===================================================== */

responder(true, [
    "id_monitor" => (int) $monitor["id_monitor"],
    "id_aluno" => (int) $monitor["id_aluno"],
    "matricula" => (string) $monitor["matricula"],
    "id_disciplinas" => (int) $monitor["id_disciplinas"],
    "nome" => (string) $monitor["nome"],
    "turma" => (string) ($monitor["turma"] ?? ""),
    "disciplina" => (string) $monitor["disciplina"],
    "area" => (string) ($monitor["area"] ?? ""),
    "foto" => $foto
]);

?>
