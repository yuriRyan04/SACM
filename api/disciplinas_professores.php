
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

mysqli_report(MYSQLI_REPORT_OFF);

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

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    responder(false, "Conexão com o banco indisponível.", [], 500);
}

$mysqli->set_charset("utf8mb4");

/* Identifica o professor que fez login. */
$matricula = $_SESSION["matricula"] ?? null;

if (empty($matricula)) {
    responder(
        false,
        "Sessão não identificada. Entre novamente no SACM.",
        [],
        401
    );
}

/* Busca o professor e as disciplinas vinculadas a ele. */
$sql = "
    SELECT
        p.id_professor,
        p.nome AS nome_professor,
        p.matricula,
        p.foto,
        d.id_disciplinas,
        d.disciplina,
        d.area
    FROM professor p
    LEFT JOIN disciplinas d
        ON FIND_IN_SET(
            CAST(d.id_disciplinas AS CHAR),
            REPLACE(COALESCE(p.id_disciplinas, ''), ' ', '')
        ) > 0
    WHERE p.matricula = ?
    ORDER BY d.disciplina ASC
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log("SACM - Erro ao preparar consulta: " . $mysqli->error);
    responder(false, "Erro ao consultar as disciplinas do professor.", [], 500);
}

$stmt->bind_param("s", $matricula);

if (!$stmt->execute()) {
    error_log("SACM - Erro ao consultar professor: " . $stmt->error);
    $stmt->close();

    responder(false, "Não foi possível carregar suas disciplinas.", [], 500);
}

$resultado = $stmt->get_result();

$professor = null;
$disciplinas = [];

while ($linha = $resultado->fetch_assoc()) {
    if ($professor === null) {
        $professor = [
            "id_professor" => (int) $linha["id_professor"],
            "nome" => $linha["nome_professor"],
            "matricula" => (string) $linha["matricula"],
            "foto" => $linha["foto"]
        ];
    }

    if ($linha["id_disciplinas"] !== null) {
        $disciplinas[] = [
            "id" => (int) $linha["id_disciplinas"],
            "id_disciplinas" => (int) $linha["id_disciplinas"],
            "nome" => $linha["disciplina"],
            "area" => $linha["area"]
        ];
    }
}

$stmt->close();

if ($professor === null) {
    responder(false, "Professor não encontrado no banco de dados.", [], 404);
}

responder(
    true,
    "Disciplinas do professor carregadas.",
    [
        "professor" => $professor,
        "disciplinas" => $disciplinas
    ]
);
?>
