
<?php

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/conexao.php";

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

if (!isset($mysqli) || !$mysqli || $mysqli->connect_error) {
    responder(false, "Erro de conexão com o banco de dados.");
}

/*
 * Recebe o ID numérico da disciplina.
 * Exemplo: monitores_disciplina.php?id_disciplinas=4
 */

$idDisciplina = filter_input(
    INPUT_GET,
    "id_disciplinas",
    FILTER_VALIDATE_INT
);

if (!$idDisciplina || $idDisciplina <= 0) {
    responder(false, "ID da disciplina ausente ou inválido.");
}

$sql = "
    SELECT
        m.id_monitor,
        m.matricula,
        m.id_disciplinas,
        a.id_aluno,
        a.Nome AS nome,
        a.serie,
        a.turma,
        a.curso,
        a.foto
    FROM monitores AS m
    INNER JOIN aluno AS a
        ON a.matricula = m.matricula
    INNER JOIN disciplinas AS d
        ON d.id_disciplinas = m.id_disciplinas
    WHERE m.id_disciplinas = ?
    ORDER BY a.Nome ASC
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    responder(
        false,
        "Erro ao preparar consulta: " . $mysqli->error
    );
}

$stmt->bind_param("i", $idDisciplina);

if (!$stmt->execute()) {
    $erro = $stmt->error;
    $stmt->close();

    responder(false, "Erro ao consultar monitores: " . $erro);
}

$resultado = $stmt->get_result();

$monitores = [];

while ($linha = $resultado->fetch_assoc()) {
    $monitores[] = [
        "id_monitor" => (int) $linha["id_monitor"],
        "id_aluno" => (int) $linha["id_aluno"],
        "matricula" => $linha["matricula"],
        "nome" => $linha["nome"],
        "serie" => $linha["serie"],
        "turma" => $linha["turma"],
        "curso" => $linha["curso"],
        "foto" => $linha["foto"],
        "id_disciplinas" => (int) $linha["id_disciplinas"]
    ];
}

$stmt->close();

responder(true, "", [
    "total" => count($monitores),
    "monitores" => $monitores
]);

?>
