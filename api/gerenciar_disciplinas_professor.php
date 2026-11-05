
<?php

header("Content-Type: application/json; charset=UTF-8");

mysqli_report(MYSQLI_REPORT_OFF);

function responder($dados, $status = 200)
{
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function obterIds($valor)
{
    if (trim((string)$valor) === "") {
        return [];
    }

    $partes = explode(",", (string)$valor);
    $ids = [];

    foreach ($partes as $parte) {
        $parte = trim($parte);

        if (
            $parte !== "" &&
            ctype_digit($parte) &&
            (int)$parte > 0
        ) {
            $ids[] = (int)$parte;
        }
    }

    return array_values(array_unique($ids));
}

try {
    require_once __DIR__ . "/conexao.php";

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new RuntimeException(
            "A conexão \$mysqli não foi encontrada."
        );
    }

    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";

    /* ======================================
       REQUISIÇÕES GET
    ====================================== */

    if ($metodo === "GET") {
        $acao = trim((string)($_GET["acao"] ?? ""));

        /* BUSCAR PROFESSOR */

        if ($acao === "buscar_professor") {
            $matricula = trim(
                (string)($_GET["matricula"] ?? "")
            );

            if ($matricula === "") {
                responder([
                    "sucesso" => false,
                    "mensagem" => "Informe a matrícula do professor."
                ], 400);
            }

            $stmt = $mysqli->prepare("
                SELECT
                    id_professor,
                    nome,
                    matricula,
                    id_disciplinas
                FROM professor
                WHERE CAST(matricula AS CHAR) = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new RuntimeException($mysqli->error);
            }

            $stmt->bind_param("s", $matricula);
            $stmt->execute();

            $resultado = $stmt->get_result();
            $professor = $resultado->fetch_assoc();

            $stmt->close();

            if (!$professor) {
                responder([
                    "sucesso" => false,
                    "mensagem" => "Nenhum professor foi encontrado com essa matrícula."
                ], 404);
            }

            responder([
                "sucesso" => true,
                "professor" => [
                    "id_professor" => (int)$professor["id_professor"],
                    "nome" => $professor["nome"],
                    "matricula" => $professor["matricula"],
                    "disciplinas_ids" => obterIds(
                        $professor["id_disciplinas"] ?? ""
                    )
                ]
            ]);
        }

        /* LISTAR ÁREAS */

        if ($acao === "areas") {
            $resultado = $mysqli->query("
                SELECT DISTINCT area
                FROM disciplinas
                WHERE area IS NOT NULL
                  AND TRIM(area) <> ''
                ORDER BY area ASC
            ");

            if (!$resultado) {
                throw new RuntimeException($mysqli->error);
            }

            $areas = [];

            while ($linha = $resultado->fetch_assoc()) {
                $areas[] = $linha["area"];
            }

            responder([
                "sucesso" => true,
                "areas" => $areas
            ]);
        }

        /* LISTAR DISCIPLINAS COM TODOS OS DETALHES */

        if ($acao === "disciplinas") {
            $area = trim(
                (string)($_GET["area"] ?? "")
            );

            if ($area !== "") {
                $stmt = $mysqli->prepare("
                    SELECT
                        id_disciplinas,
                        disciplina,
                        area,
                        series,
                        cursos,
                        limite_monitores
                    FROM disciplinas
                    WHERE area = ?
                    ORDER BY disciplina ASC
                ");

                if (!$stmt) {
                    throw new RuntimeException($mysqli->error);
                }

                $stmt->bind_param("s", $area);
                $stmt->execute();

                $resultado = $stmt->get_result();

            } else {
                $stmt = null;

                $resultado = $mysqli->query("
                    SELECT
                        id_disciplinas,
                        disciplina,
                        area,
                        series,
                        cursos,
                        limite_monitores
                    FROM disciplinas
                    ORDER BY disciplina ASC
                ");

                if (!$resultado) {
                    throw new RuntimeException($mysqli->error);
                }
            }

            $disciplinas = [];

            while ($linha = $resultado->fetch_assoc()) {
                $disciplinas[] = [
                    "id_disciplinas" => (int)$linha["id_disciplinas"],
                    "disciplina" => $linha["disciplina"],
                    "area" => $linha["area"] ?? "",
                    "series" => $linha["series"] ?? "",
                    "cursos" => $linha["cursos"] ?? "",
                    "limite_monitores" => $linha["limite_monitores"] ?? null
                ];
            }

            if ($stmt !== null) {
                $stmt->close();
            }

            responder([
                "sucesso" => true,
                "disciplinas" => $disciplinas
            ]);
        }

        responder([
            "sucesso" => false,
            "mensagem" => "Ação GET inválida."
        ], 400);
    }

    /* ======================================
       REQUISIÇÕES POST
    ====================================== */

    if ($metodo === "POST") {
        $entrada = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!is_array($entrada)) {
            responder([
                "sucesso" => false,
                "mensagem" => "Os dados enviados são inválidos."
            ], 400);
        }

        $acao = trim(
            (string)($entrada["acao"] ?? "")
        );

        if ($acao !== "salvar") {
            responder([
                "sucesso" => false,
                "mensagem" => "Ação POST inválida."
            ], 400);
        }

        $idProfessor = filter_var(
            $entrada["id_professor"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$idProfessor || $idProfessor < 1) {
            responder([
                "sucesso" => false,
                "mensagem" => "Identificação do professor inválida."
            ], 400);
        }

        $idsRecebidos = $entrada["disciplinas"] ?? [];

        if (!is_array($idsRecebidos)) {
            responder([
                "sucesso" => false,
                "mensagem" => "A lista de disciplinas é inválida."
            ], 400);
        }

        $ids = [];

        foreach ($idsRecebidos as $id) {
            $idValidado = filter_var(
                $id,
                FILTER_VALIDATE_INT
            );

            if (!$idValidado || $idValidado < 1) {
                responder([
                    "sucesso" => false,
                    "mensagem" => "Uma das disciplinas selecionadas é inválida."
                ], 400);
            }

            $ids[] = (int)$idValidado;
        }

        $ids = array_values(array_unique($ids));

        /* CONFIRMAR PROFESSOR */

        $stmt = $mysqli->prepare("
            SELECT id_professor
            FROM professor
            WHERE id_professor = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param("i", $idProfessor);
        $stmt->execute();

        $professorExiste =
            $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if (!$professorExiste) {
            responder([
                "sucesso" => false,
                "mensagem" => "O professor selecionado não existe."
            ], 404);
        }

        /* VALIDAR DISCIPLINAS */

        if (count($ids) > 0) {
            $marcadores = implode(
                ",",
                array_fill(0, count($ids), "?")
            );

            $tipos = str_repeat("i", count($ids));

            $sql = "
                SELECT COUNT(DISTINCT id_disciplinas) AS total
                FROM disciplinas
                WHERE id_disciplinas IN ($marcadores)
            ";

            $stmt = $mysqli->prepare($sql);

            if (!$stmt) {
                throw new RuntimeException($mysqli->error);
            }

            $stmt->bind_param($tipos, ...$ids);
            $stmt->execute();

            $totalEncontrado = (int)$stmt
                ->get_result()
                ->fetch_assoc()["total"];

            $stmt->close();

            if ($totalEncontrado !== count($ids)) {
                responder([
                    "sucesso" => false,
                    "mensagem" => "Uma ou mais disciplinas não existem no banco de dados."
                ], 400);
            }
        }

        /* SALVAR DISCIPLINAS NO PROFESSOR */

        $valorDisciplinas = implode(",", $ids);

        $stmt = $mysqli->prepare("
            UPDATE professor
            SET id_disciplinas = ?
            WHERE id_professor = ?
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param(
            "si",
            $valorDisciplinas,
            $idProfessor
        );

        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }

        $stmt->close();

        responder([
            "sucesso" => true,
            "mensagem" => "Disciplinas do professor salvas com sucesso."
        ]);
    }

    responder([
        "sucesso" => false,
        "mensagem" => "Método não permitido."
    ], 405);

} catch (Throwable $e) {
    error_log(
        "gerenciar_disciplinas_professor.php: " .
        $e->getMessage()
    );

    responder([
        "sucesso" => false,
        "mensagem" => "Erro interno ao processar a solicitação. Confira o log do PHP."
    ], 500);
}
?>
