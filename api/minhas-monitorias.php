<?php
/*
 * SACM - API Minhas Monitorias
 * Retorna disciplinas e agendamentos do aluno que atua como monitor.
 * Inclui série, turma e curso dos alunos agendados.
 */

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once __DIR__ . "/conexao.php";

/* =====================================================
   RESPOSTA JSON
===================================================== */

function responder(
    $sucesso,
    $mensagem = "",
    array $dados = [],
    $status = 200
) {
    http_response_code($status);

    echo json_encode(
        array_merge([
            "sucesso" => (bool) $sucesso,
            "mensagem" => (string) $mensagem
        ], $dados),
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/* =====================================================
   NORMALIZAR TEXTO
===================================================== */

function normalizarTexto($texto)
{
    $texto = trim((string) $texto);

    return function_exists("mb_strtolower")
        ? mb_strtolower($texto, "UTF-8")
        : strtolower($texto);
}

/* =====================================================
   EXECUÇÃO
===================================================== */

try {

    if (
        !isset($mysqli) ||
        !($mysqli instanceof mysqli)
    ) {
        throw new RuntimeException(
            "A conexão \$mysqli não foi criada em conexao.php."
        );
    }

    if ($mysqli->connect_errno) {
        throw new RuntimeException(
            "Falha na conexão com o banco de dados."
        );
    }

    $mysqli->set_charset("utf8mb4");

    /* =================================================
       1. MATRÍCULA DO MONITOR
    ================================================= */

    $matricula = trim(
        (string) ($_GET["matricula"] ?? "")
    );

    if ($matricula === "") {
        responder(
            false,
            "Matrícula não informada.",
            [],
            400
        );
    }

    /* =================================================
       2. DISCIPLINAS EM QUE O ALUNO É MONITOR
    ================================================= */

    $sql = "
        SELECT
            m.id_monitor,
            m.matricula,
            m.id_disciplinas,
            m.id_aluno,
            d.disciplina,
            d.area
        FROM monitores AS m
        INNER JOIN disciplinas AS d
            ON CAST(d.id_disciplinas AS CHAR) =
               TRIM(m.id_disciplinas)
        WHERE TRIM(m.matricula) = ?
        ORDER BY d.disciplina
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            "Erro ao preparar consulta dos monitores: " .
            $mysqli->error
        );
    }

    $stmt->bind_param("s", $matricula);

    if (!$stmt->execute()) {
        throw new RuntimeException(
            "Erro ao consultar monitores: " .
            $stmt->error
        );
    }

    $resultado = $stmt->get_result();

    $disciplinas = [];
    $idsMonitor = [];

    while ($linha = $resultado->fetch_assoc()) {

        $idMonitor = (int) $linha["id_monitor"];
        $idDisciplina = (int) $linha["id_disciplinas"];

        $idsMonitor[] = $idMonitor;

        $disciplinas[] = [
            "id_monitor" => $idMonitor,
            "id_disciplinas" => $idDisciplina,
            "matricula" => (string) $linha["matricula"],
            "id_aluno" => (int) $linha["id_aluno"],
            "disciplina" => (string) $linha["disciplina"],
            "area" => (string) ($linha["area"] ?? "")
        ];
    }

    $stmt->close();

    $idsMonitor = array_values(
        array_unique($idsMonitor)
    );

    /* =================================================
       3. NENHUMA DISCIPLINA ENCONTRADA
    ================================================= */

    if (empty($idsMonitor)) {

        responder(
            true,
            "Nenhuma disciplina encontrada para esta matrícula.",
            [
                "monitor" => [
                    "matricula" => $matricula,
                    "ids_monitor" => []
                ],
                "disciplinas" => [],
                "agendamentos" => [],
                "monitorias" => [],
                "total" => 0,
                "total_agendados" => 0,
                "total_realizados" => 0
            ]
        );
    }

    /* =================================================
       4. BUSCAR AGENDAMENTOS
       
       IMPORTANTE:
       A tabela aluno precisa possuir as colunas:
       serie, turma e curso.
    ================================================= */

    $placeholders = implode(
        ",",
        array_fill(0, count($idsMonitor), "?")
    );

    $sql = "
        SELECT
            ag.id_agendamento,
            ag.id_aluno,
            ag.id_monitor,
            ag.id_disciplinas,

            d.disciplina AS disciplina_banco,
            d.area,

            al.Nome AS aluno_nome,

            al.serie AS aluno_serie,
            al.turma AS aluno_turma,
            al.curso AS aluno_curso,

            ag.data_agendamento,
            ag.hora_inicio,
            ag.assunto,
            ag.observacao,
            ag.status

        FROM agendamentos AS ag

        INNER JOIN monitores AS m
            ON m.id_monitor = ag.id_monitor

        LEFT JOIN disciplinas AS d
            ON d.id_disciplinas = ag.id_disciplinas

        LEFT JOIN aluno AS al
            ON al.id_aluno = ag.id_aluno

        WHERE ag.id_monitor IN ($placeholders)

        ORDER BY
            ag.data_agendamento ASC,
            ag.hora_inicio ASC,
            ag.id_agendamento ASC
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            "Erro ao preparar consulta de agendamentos: " .
            $mysqli->error
        );
    }

    $tipos = str_repeat(
        "i",
        count($idsMonitor)
    );

    $stmt->bind_param(
        $tipos,
        ...$idsMonitor
    );

    if (!$stmt->execute()) {
        throw new RuntimeException(
            "Erro ao consultar agendamentos: " .
            $stmt->error
        );
    }

    $resultado = $stmt->get_result();

    $agendamentos = [];

    while ($linha = $resultado->fetch_assoc()) {

        $agendamentos[] = [
            "id_agendamento" =>
                (int) $linha["id_agendamento"],

            "id_aluno" =>
                (int) $linha["id_aluno"],

            "id_monitor" =>
                (int) $linha["id_monitor"],

            "id_disciplinas" =>
                (int) $linha["id_disciplinas"],

            "disciplina" =>
                (string) ($linha["disciplina_banco"] ?? ""),

            "disciplina_banco" =>
                (string) ($linha["disciplina_banco"] ?? ""),

            "area" =>
                (string) ($linha["area"] ?? ""),

            "aluno_nome" =>
                (string) ($linha["aluno_nome"] ?? "Aluno não identificado"),

            /* Série do aluno */

            "aluno_serie" =>
                (string) ($linha["aluno_serie"] ?? ""),

            "serie" =>
                (string) ($linha["aluno_serie"] ?? ""),

            /* Turma do aluno */

            "aluno_turma" =>
                (string) ($linha["aluno_turma"] ?? ""),

            "turma" =>
                (string) ($linha["aluno_turma"] ?? ""),

            /* Curso do aluno */

            "aluno_curso" =>
                (string) ($linha["aluno_curso"] ?? ""),

            "curso" =>
                (string) ($linha["aluno_curso"] ?? ""),

            /* Dados do agendamento */

            "data_agendamento" =>
                (string) $linha["data_agendamento"],

            "hora_inicio" =>
                (string) $linha["hora_inicio"],

            "assunto" =>
                (string) ($linha["assunto"] ?? ""),

            "observacao" =>
                (string) ($linha["observacao"] ?? ""),

            "status" =>
                (string) ($linha["status"] ?? "")
        ];
    }

    $stmt->close();

    /* =================================================
       5. CALCULAR CONTADORES
    ================================================= */

    $totalAgendados = 0;
    $totalRealizados = 0;

    foreach ($agendamentos as $agendamento) {

        $status = normalizarTexto(
            $agendamento["status"]
        );

        if ($status === "agendado") {
            $totalAgendados++;
        } elseif ($status === "realizado") {
            $totalRealizados++;
        }
    }

    /* =================================================
       6. RETORNAR JSON
    ================================================= */

    responder(
        true,
        "Consulta realizada.",
        [
            "monitor" => [
                "matricula" => $matricula,
                "ids_monitor" => $idsMonitor
            ],

            "disciplinas" => $disciplinas,

            "agendamentos" => $agendamentos,

            "monitorias" => $agendamentos,

            "total" => count($agendamentos),

            "total_agendados" => $totalAgendados,

            "total_realizados" => $totalRealizados
        ]
    );

} catch (Throwable $e) {

    error_log(
        "Erro em api/minhas-monitorias.php: " .
        $e->getMessage()
    );

    responder(
        false,
        "Erro ao consultar as monitorias. Verifique o log do PHP.",
        [],
        500
    );
}
?>