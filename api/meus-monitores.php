
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

try {

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        responder(false, "Conexão com o banco indisponível.", [], 500);
    }

    $mysqli->set_charset("utf8mb4");

    // Verifica a sessão do professor.
    $matricula = trim((string) ($_SESSION["matricula"] ?? ""));
    $tipo = strtolower(trim((string) ($_SESSION["tipo"] ?? "")));

    if ($matricula === "") {
        responder(
            false,
            "Sessão não encontrada. Entre novamente no SACM.",
            [],
            401
        );
    }

    if ($tipo !== "professor") {
        responder(
            false,
            "Acesso permitido somente para professores.",
            [],
            403
        );
    }

    // Recebe o ID da disciplina.
    $idDisciplina = filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );

    if (!$idDisciplina || $idDisciplina < 1) {
        responder(false, "ID da disciplina inválido.", [], 400);
    }

    // Verifica se a disciplina pertence ao professor.
    $sqlPermissao = "
        SELECT
            d.id_disciplinas,
            d.disciplina,
            d.area
        FROM professor p
        INNER JOIN disciplinas d
            ON FIND_IN_SET(
                CAST(d.id_disciplinas AS CHAR),
                REPLACE(
                    COALESCE(p.id_disciplinas, ''),
                    ' ',
                    ''
                )
            ) > 0
        WHERE CAST(p.matricula AS CHAR) = ?
          AND d.id_disciplinas = ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sqlPermissao);

    if (!$stmt) {
        error_log("SACM permissao prepare: " . $mysqli->error);
        responder(false, "Erro ao verificar a disciplina.", [], 500);
    }

    $stmt->bind_param("si", $matricula, $idDisciplina);

    if (!$stmt->execute()) {
        error_log("SACM permissao execute: " . $stmt->error);
        $stmt->close();

        responder(false, "Erro ao verificar a disciplina.", [], 500);
    }

    $resultadoPermissao = $stmt->get_result();
    $disciplina = $resultadoPermissao->fetch_assoc();

    $stmt->close();

    if (!$disciplina) {
        responder(
            false,
            "Disciplina não vinculada ao seu perfil.",
            [],
            403
        );
    }

    // Busca os monitores e monta a informação completa da turma.
    $sqlMonitores = "
        SELECT DISTINCT
            a.Nome AS nome,
            a.matricula AS matricula,

            TRIM(
                CONCAT_WS(
                    ' ',
                    NULLIF(TRIM(COALESCE(a.serie, '')), ''),
                    NULLIF(TRIM(COALESCE(a.turma, '')), ''),
                    NULLIF(TRIM(COALESCE(a.curso, '')), '')
                )
            ) AS turma,

            d.disciplina AS disciplina

        FROM monitores m

        INNER JOIN disciplinas d
            ON d.id_disciplinas = m.id_disciplinas

        INNER JOIN aluno a
            ON CAST(a.matricula AS CHAR)
             = CAST(m.matricula AS CHAR)

        WHERE m.id_disciplinas = ?

        ORDER BY a.Nome ASC
    ";

    $stmt = $mysqli->prepare($sqlMonitores);

    if (!$stmt) {
        error_log("SACM monitores prepare: " . $mysqli->error);
        responder(
            false,
            "Erro ao preparar a consulta dos monitores.",
            [],
            500
        );
    }

    $stmt->bind_param("i", $idDisciplina);

    if (!$stmt->execute()) {
        error_log("SACM monitores execute: " . $stmt->error);
        $stmt->close();

        responder(false, "Erro ao consultar os monitores.", [], 500);
    }

    $resultado = $stmt->get_result();

    $monitores = [];

    while ($linha = $resultado->fetch_assoc()) {
        $monitores[] = [
            "nome" => $linha["nome"] ?? "",
            "matricula" => (string) ($linha["matricula"] ?? ""),
            "turma" => $linha["turma"] ?? "",
            "disciplina" => $linha["disciplina"] ?? ""
        ];
    }

    $stmt->close();

    responder(
        true,
        "Consulta concluída.",
        [
            "disciplina" => [
                "id" => (int) $disciplina["id_disciplinas"],
                "nome" => $disciplina["disciplina"],
                "area" => $disciplina["area"]
            ],
            "monitores" => $monitores
        ]
    );

} catch (Throwable $erro) {

    error_log(
        "SACM meus-monitores: " .
        get_class($erro) . ": " .
        $erro->getMessage()
    );

    responder(
        false,
        "Erro interno ao consultar os monitores.",
        [],
        500
    );
}
?>
