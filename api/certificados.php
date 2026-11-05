
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/permissao_monitor.php";

// =====================================================
// RESPOSTA JSON
// =====================================================

function responder($sucesso, $mensagem, $dados = []) {
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

    // =================================================
    // VERIFICAR PERMISSÕES
    // =================================================

    sacmExigirUsuario();

    if (!sacmEhGestao()) {
        http_response_code(403);

        responder(
            false,
            "Somente a Gestão pode gerenciar certificados."
        );
    }

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new Exception(
            "Conexão com o banco indisponível."
        );
    }

    $mysqli->set_charset("utf8mb4");

    $metodo = $_SERVER["REQUEST_METHOD"];
    $acao = $_GET["acao"] ?? $_POST["acao"] ?? "";

    // =================================================
    // LISTAR DISCIPLINAS
    // GET: api/certificados.php?acao=disciplinas
    // =================================================

    if ($metodo === "GET" && $acao === "disciplinas") {

        $sql = "
            SELECT
                id_disciplinas,
                disciplina,
                area
            FROM disciplinas
            ORDER BY disciplina ASC
        ";

        $resultado = $mysqli->query($sql);

        if (!$resultado) {
            throw new Exception(
                "Não foi possível carregar as disciplinas."
            );
        }

        $disciplinas = [];

        while ($linha = $resultado->fetch_assoc()) {
            $disciplinas[] = $linha;
        }

        $resultado->free();

        responder(
            true,
            "Disciplinas carregadas.",
            [
                "disciplinas" => $disciplinas
            ]
        );
    }

    // =================================================
    // LISTAR MONITORES E DADOS ATUAIS DOS CERTIFICADOS
    // GET: api/certificados.php?acao=monitores&id_disciplinas=1
    // =================================================

    if ($metodo === "GET" && $acao === "monitores") {

        $idDisciplina = filter_input(
            INPUT_GET,
            "id_disciplinas",
            FILTER_VALIDATE_INT
        );

        if (!$idDisciplina || $idDisciplina < 1) {
            http_response_code(400);

            responder(
                false,
                "Selecione uma disciplina válida."
            );
        }

        $sql = "
            SELECT
                m.id_monitor,
                a.Nome AS nome_aluno,
                a.matricula AS matricula_aluno,
                d.disciplina,
                cm.id_certificado,
                cm.carga_horaria,
                cm.periodo,
                cm.data_emissao
            FROM monitores m

            INNER JOIN aluno a
                ON a.id_aluno = m.id_aluno

            INNER JOIN disciplinas d
                ON d.id_disciplinas = m.id_disciplinas

            LEFT JOIN certificados_monitoria cm
                ON cm.id_monitor = m.id_monitor
                AND cm.id_disciplinas = m.id_disciplinas

            WHERE m.id_disciplinas = ?

            ORDER BY a.Nome ASC
        ";

        $stmt = $mysqli->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                "Não foi possível preparar a consulta dos monitores."
            );
        }

        $stmt->bind_param("i", $idDisciplina);

        if (!$stmt->execute()) {
            throw new Exception(
                "Não foi possível consultar os monitores."
            );
        }

        $resultado = $stmt->get_result();

        $monitores = [];

        while ($linha = $resultado->fetch_assoc()) {
            $monitores[] = $linha;
        }

        $stmt->close();

        responder(
            true,
            "Consulta concluída com os dados atuais.",
            [
                "monitores" => $monitores
            ]
        );
    }

    // =================================================
    // SALVAR OU ATUALIZAR DADOS DO CERTIFICADO
    // POST: acao=salvar
    // =================================================

    if ($metodo === "POST" && $acao === "salvar") {

        $idMonitor = filter_input(
            INPUT_POST,
            "id_monitor",
            FILTER_VALIDATE_INT
        );

        $idDisciplina = filter_input(
            INPUT_POST,
            "id_disciplinas",
            FILTER_VALIDATE_INT
        );

        $cargaHoraria = filter_input(
            INPUT_POST,
            "carga_horaria",
            FILTER_VALIDATE_INT
        );

        $periodo = trim($_POST["periodo"] ?? "");

        // ---------------------------------------------
        // VALIDAR MONITOR
        // ---------------------------------------------

        if (!$idMonitor || $idMonitor < 1) {
            http_response_code(400);

            responder(
                false,
                "Monitor inválido."
            );
        }

        // ---------------------------------------------
        // VALIDAR DISCIPLINA
        // ---------------------------------------------

        if (!$idDisciplina || $idDisciplina < 1) {
            http_response_code(400);

            responder(
                false,
                "Disciplina inválida."
            );
        }

        // ---------------------------------------------
        // VALIDAR CARGA HORÁRIA
        // ---------------------------------------------

        if (
            $cargaHoraria === false ||
            $cargaHoraria < 1 ||
            $cargaHoraria > 10000
        ) {
            http_response_code(400);

            responder(
                false,
                "Informe uma carga horária válida."
            );
        }

        // ---------------------------------------------
        // VALIDAR PERÍODO
        // ---------------------------------------------

        $periodosPermitidos = [
            "1º",
            "2º",
            "1º e 2º"
        ];

        if (!in_array($periodo, $periodosPermitidos, true)) {
            http_response_code(400);

            responder(
                false,
                "Selecione um semestre válido."
            );
        }

        // ---------------------------------------------
        // CONFIRMAR SE O MONITOR PERTENCE À DISCIPLINA
        // ---------------------------------------------

        $sqlVerificar = "
            SELECT id_monitor
            FROM monitores
            WHERE id_monitor = ?
              AND id_disciplinas = ?
            LIMIT 1
        ";

        $stmt = $mysqli->prepare($sqlVerificar);

        if (!$stmt) {
            throw new Exception(
                "Não foi possível verificar o monitor."
            );
        }

        $stmt->bind_param(
            "ii",
            $idMonitor,
            $idDisciplina
        );

        if (!$stmt->execute()) {
            throw new Exception(
                "Erro ao verificar o cadastro do monitor."
            );
        }

        $monitorExiste = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if (!$monitorExiste) {
            http_response_code(404);

            responder(
                false,
                "O monitor não está cadastrado nessa disciplina."
            );
        }

        // ---------------------------------------------
        // INSERIR OU ATUALIZAR O REGISTRO EXISTENTE
        //
        // A chave única certificado_monitor_disciplina
        // identifica a combinação:
        // id_monitor + id_disciplinas.
        //
        // Quando o registro já existe, os novos valores
        // substituem os valores anteriormente salvos.
        // ---------------------------------------------

        $sqlSalvar = "
            INSERT INTO certificados_monitoria (
                id_monitor,
                id_disciplinas,
                carga_horaria,
                periodo,
                data_emissao,
                emitido_por
            )
            VALUES (
                ?, ?, ?, ?, CURDATE(), 'gestao'
            )
            ON DUPLICATE KEY UPDATE
                carga_horaria = ?,
                periodo = ?,
                data_emissao = CURDATE(),
                emitido_por = 'gestao'
        ";

        $stmt = $mysqli->prepare($sqlSalvar);

        if (!$stmt) {
            throw new Exception(
                "Não foi possível preparar o salvamento."
            );
        }

        // Os valores são enviados novamente para garantir
        // a atualização da carga horária e do período.

        $stmt->bind_param(
            "iiisis",
            $idMonitor,
            $idDisciplina,
            $cargaHoraria,
            $periodo,
            $cargaHoraria,
            $periodo
        );

        if (!$stmt->execute()) {
            $erroBanco = $stmt->error;

            $stmt->close();

            error_log(
                "SACM certificados.php - Erro ao salvar: " .
                $erroBanco
            );

            throw new Exception(
                "Não foi possível salvar os dados do certificado."
            );
        }

        $stmt->close();

        // ---------------------------------------------
        // RETORNAR OS VALORES EFETIVAMENTE SALVOS
        // ---------------------------------------------

        $sqlAtual = "
            SELECT
                id_certificado,
                id_monitor,
                id_disciplinas,
                carga_horaria,
                periodo,
                data_emissao,
                emitido_por
            FROM certificados_monitoria
            WHERE id_monitor = ?
              AND id_disciplinas = ?
            LIMIT 1
        ";

        $stmt = $mysqli->prepare($sqlAtual);

        if (!$stmt) {
            throw new Exception(
                "Os dados foram salvos, mas não foi possível confirmar a atualização."
            );
        }

        $stmt->bind_param(
            "ii",
            $idMonitor,
            $idDisciplina
        );

        if (!$stmt->execute()) {
            throw new Exception(
                "Os dados foram salvos, mas não foi possível consultar o registro atualizado."
            );
        }

        $resultado = $stmt->get_result();
        $certificado = $resultado->fetch_assoc();

        $stmt->close();

        if (!$certificado) {
            throw new Exception(
                "Não foi possível localizar o certificado atualizado."
            );
        }

        responder(
            true,
            "Dados do certificado atualizados com sucesso.",
            [
                "certificado" => $certificado,
                "id_monitor" => (int) $certificado["id_monitor"],
                "id_disciplinas" => (int) $certificado["id_disciplinas"],
                "carga_horaria" => (int) $certificado["carga_horaria"],
                "periodo" => $certificado["periodo"],
                "data_emissao" => $certificado["data_emissao"]
            ]
        );
    }

    // =================================================
    // AÇÃO INVÁLIDA
    // =================================================

    http_response_code(400);

    responder(
        false,
        "Ação inválida."
    );

} catch (Throwable $erro) {

    error_log(
        "SACM certificados.php: " .
        $erro->getMessage()
    );

    if (!headers_sent()) {
        http_response_code(500);
    }

    responder(
        false,
        "Ocorreu um erro ao processar os certificados."
    );
}

?>
