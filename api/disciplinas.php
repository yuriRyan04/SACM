
<?php

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once __DIR__ . "/conexao.php";

$areasPermitidas = [
    "LINGUAGENS",
    "NATUREZA",
    "HUMANAS",
    "MATEMÁTICA",
    "Base Técnica / INFORMÁTICA",
    "Base Técnica / REDES"
];

$seriesPermitidas = [
    "1ª série",
    "2ª série",
    "3ª série"
];

$cursosPermitidos = [
    "Informática",
    "Redes"
];

/* RESPOSTA JSON */

function responder($sucesso, $mensagem = "", $dados = null)
{
    $resposta = [
        "sucesso" => $sucesso
    ];

    if ($mensagem !== "") {
        $resposta["mensagem"] = $mensagem;
    }

    if ($dados !== null) {
        $resposta = array_merge($resposta, $dados);
    }

    echo json_encode(
        $resposta,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/* CONEXÃO */

if (
    !isset($mysqli) ||
    !$mysqli ||
    $mysqli->connect_error
) {
    responder(false, "Erro na conexão com o banco de dados.");
}

/* NORMALIZAR SÉRIES E CURSOS */

function normalizarOpcoes($entrada, array $permitidas, $universal)
{
    if (is_string($entrada)) {
        $entrada = [$entrada];
    }

    if (!is_array($entrada)) {
        return null;
    }

    $selecionadas = [];

    foreach ($entrada as $valor) {
        if (!is_string($valor)) {
            continue;
        }

        $valor = trim($valor);

        if ($valor === $universal) {
            return $universal;
        }

        if (
            in_array($valor, $permitidas, true) &&
            !in_array($valor, $selecionadas, true)
        ) {
            $selecionadas[] = $valor;
        }
    }

    if (count($selecionadas) === 0) {
        return null;
    }

    return implode(", ", $selecionadas);
}

/* VERIFICAR DUPLICIDADE */

function disciplinaDuplicada(
    $mysqli,
    $nome,
    $area,
    $idExcluir = 0
) {
    if ($idExcluir > 0) {
        $stmt = $mysqli->prepare("
            SELECT id_disciplinas
            FROM disciplinas
            WHERE disciplina = ?
              AND area = ?
              AND id_disciplinas <> ?
        ");

        if (!$stmt) {
            responder(false, "Erro ao verificar duplicidade.");
        }

        $stmt->bind_param(
            "ssi",
            $nome,
            $area,
            $idExcluir
        );

    } else {
        $stmt = $mysqli->prepare("
            SELECT id_disciplinas
            FROM disciplinas
            WHERE disciplina = ?
              AND area = ?
        ");

        if (!$stmt) {
            responder(false, "Erro ao verificar duplicidade.");
        }

        $stmt->bind_param("ss", $nome, $area);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        responder(false, "Erro ao consultar disciplinas.");
    }

    $stmt->store_result();

    $duplicada = $stmt->num_rows > 0;

    $stmt->close();

    return $duplicada;
}

$metodo = $_SERVER["REQUEST_METHOD"] ?? "";

/* =====================================================
   GET — CONSULTAR UMA DISCIPLINA OU LISTAR TODAS
===================================================== */

if ($metodo === "GET") {

    /* CONSULTAR POR ID */

    if (isset($_GET["id"])) {
        $id = filter_var(
            $_GET["id"],
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id <= 0) {
            responder(false, "ID da disciplina inválido.");
        }

        $stmt = $mysqli->prepare("
            SELECT
                id_disciplinas,
                disciplina,
                area,
                series,
                cursos,
                limite_monitores
            FROM disciplinas
            WHERE id_disciplinas = ?
        ");

        if (!$stmt) {
            responder(false, "Erro ao preparar consulta.");
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $linha = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$linha) {
            responder(false, "Disciplina não encontrada.");
        }

        responder(true, "", [
            "id" => (int)$linha["id_disciplinas"],
            "id_disciplinas" => (int)$linha["id_disciplinas"],
            "nome" => $linha["disciplina"],
            "disciplina" => $linha["disciplina"],
            "area" => $linha["area"],
            "series" => $linha["series"] ?? "Todas",
            "cursos" => $linha["cursos"] ?? "Todos",
            "limite_monitores" => (int)$linha["limite_monitores"]
        ]);
    }

    /*
     * SEM ?area= RETORNA TODAS AS ÁREAS.
     * SE ?area= FOR INFORMADO, FILTRA PELA ÁREA.
     *
     * Os filtros serie e curso continuam disponíveis
     * para outras páginas do SACM.
     */

    $area = trim($_GET["area"] ?? "");
    $serieAluno = trim($_GET["serie"] ?? "");
    $cursoAluno = trim($_GET["curso"] ?? "");

    if (
        $area !== "" &&
        !in_array($area, $areasPermitidas, true)
    ) {
        responder(false, "Área inválida.");
    }

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
            ORDER BY area ASC, disciplina ASC
        ");

        if (!$stmt) {
            responder(false, "Erro ao preparar consulta.");
        }

        $stmt->bind_param("s", $area);

        if (!$stmt->execute()) {
            $stmt->close();
            responder(false, "Erro ao consultar disciplinas.");
        }

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
            ORDER BY area ASC, disciplina ASC
        ");

        if (!$resultado) {
            responder(false, "Erro ao consultar disciplinas.");
        }
    }

    $lista = [];

    while ($linha = $resultado->fetch_assoc()) {

        $seriesSalvas = $linha["series"] ?? "Todas";
        $cursosSalvos = $linha["cursos"] ?? "Todos";

        /*
         * Os filtros de aluno são opcionais.
         * Sem serie e curso na URL, nenhuma disciplina
         * é filtrada por esses campos.
         */

        if ($serieAluno !== "") {
            if (
                trim($seriesSalvas) !== "Todas" &&
                !in_array(
                    $serieAluno,
                    array_map("trim", explode(",", $seriesSalvas)),
                    true
                )
            ) {
                continue;
            }
        }

        if ($cursoAluno !== "") {
            if (
                trim($cursosSalvos) !== "Todos" &&
                !in_array(
                    $cursoAluno,
                    array_map("trim", explode(",", $cursosSalvos)),
                    true
                )
            ) {
                continue;
            }
        }

        $lista[] = [
            "id" => (int)$linha["id_disciplinas"],
            "id_disciplinas" => (int)$linha["id_disciplinas"],
            "nome" => $linha["disciplina"],
            "disciplina" => $linha["disciplina"],
            "area" => $linha["area"],
            "series" => $seriesSalvas,
            "cursos" => $cursosSalvos,
            "limite_monitores" => (int)$linha["limite_monitores"]
        ];
    }

    if ($stmt !== null) {
        $stmt->close();
    }

    echo json_encode(
        $lista,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/* =====================================================
   POST — CADASTRAR, EDITAR OU EXCLUIR
===================================================== */

if ($metodo === "POST") {

    /* CADASTRAR OU EDITAR */

    if (
        isset($_POST["adicionar"]) ||
        isset($_POST["editar"])
    ) {
        $editando = isset($_POST["editar"]);

        $nome = trim($_POST["nome"] ?? "");
        $area = trim($_POST["area"] ?? "");

        if ($nome === "") {
            responder(false, "Digite o nome da disciplina.");
        }

        if (mb_strlen($nome, "UTF-8") > 100) {
            responder(
                false,
                "O nome deve ter no máximo 100 caracteres."
            );
        }

        if (!in_array($area, $areasPermitidas, true)) {
            responder(false, "Selecione uma área válida.");
        }

        $series = normalizarOpcoes(
            $_POST["series"] ?? null,
            $seriesPermitidas,
            "Todas"
        );

        if ($series === null) {
            responder(
                false,
                "Selecione pelo menos uma série ou marque Todas as séries."
            );
        }

        $cursos = normalizarOpcoes(
            $_POST["cursos"] ?? null,
            $cursosPermitidos,
            "Todos"
        );

        if ($cursos === null) {
            responder(
                false,
                "Selecione pelo menos um curso ou marque Todos os cursos."
            );
        }

        $limite = filter_var(
            $_POST["limite_monitores"] ?? 2,
            FILTER_VALIDATE_INT
        );

        if (
            $limite === false ||
            $limite < 1 ||
            $limite > 100
        ) {
            responder(
                false,
                "O limite deve ser um número entre 1 e 100."
            );
        }

        $id = 0;

        if ($editando) {
            $id = filter_var(
                $_POST["id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id === null || $id <= 0) {
                responder(
                    false,
                    "ID da disciplina inválido para edição."
                );
            }
        }

        if (disciplinaDuplicada($mysqli, $nome, $area, $id)) {
            responder(
                false,
                "Essa disciplina já está cadastrada nessa área."
            );
        }

        /* ATUALIZAR */

        if ($editando) {
            $stmt = $mysqli->prepare("
                UPDATE disciplinas
                SET disciplina = ?,
                    area = ?,
                    series = ?,
                    cursos = ?,
                    limite_monitores = ?
                WHERE id_disciplinas = ?
            ");

            if (!$stmt) {
                responder(false, "Erro ao preparar edição.");
            }

            $stmt->bind_param(
                "ssssii",
                $nome,
                $area,
                $series,
                $cursos,
                $limite,
                $id
            );

            if (!$stmt->execute()) {
                $erro = $stmt->error;
                $stmt->close();

                responder(
                    false,
                    "Erro ao editar disciplina: " . $erro
                );
            }

            $afetadas = $stmt->affected_rows;
            $stmt->close();

            if ($afetadas === 0) {
                $verificacao = $mysqli->prepare("
                    SELECT id_disciplinas
                    FROM disciplinas
                    WHERE id_disciplinas = ?
                ");

                if (!$verificacao) {
                    responder(false, "Erro ao confirmar edição.");
                }

                $verificacao->bind_param("i", $id);
                $verificacao->execute();
                $verificacao->store_result();

                $existe = $verificacao->num_rows > 0;
                $verificacao->close();

                if (!$existe) {
                    responder(false, "Disciplina não encontrada.");
                }
            }

            responder(true, "Disciplina atualizada com sucesso!", [
                "id" => $id,
                "id_disciplinas" => $id,
                "nome" => $nome,
                "area" => $area,
                "series" => $series,
                "cursos" => $cursos,
                "limite_monitores" => $limite
            ]);
        }

        /* INSERIR */

        $stmt = $mysqli->prepare("
            INSERT INTO disciplinas
                (disciplina, area, series, cursos, limite_monitores)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            responder(false, "Erro ao preparar cadastro.");
        }

        $stmt->bind_param(
            "ssssi",
            $nome,
            $area,
            $series,
            $cursos,
            $limite
        );

        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();

            responder(
                false,
                "Erro ao cadastrar disciplina: " . $erro
            );
        }

        $idNovo = (int)$stmt->insert_id;

        $stmt->close();

        responder(true, "Disciplina cadastrada com sucesso!", [
            "id" => $idNovo,
            "id_disciplinas" => $idNovo,
            "nome" => $nome,
            "area" => $area,
            "series" => $series,
            "cursos" => $cursos,
            "limite_monitores" => $limite
        ]);
    }

    /* EXCLUIR */

    if (isset($_POST["excluir"])) {
        $id = filter_var(
            $_POST["excluir"],
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null || $id <= 0) {
            responder(false, "ID da disciplina inválido.");
        }

        $stmt = $mysqli->prepare("
            DELETE FROM disciplinas
            WHERE id_disciplinas = ?
        ");

        if (!$stmt) {
            responder(false, "Erro ao preparar exclusão.");
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();

            responder(
                false,
                "Não foi possível excluir a disciplina. "
                . "Verifique se ela está vinculada a outros registros. "
                . $erro
            );
        }

        $afetadas = $stmt->affected_rows;

        $stmt->close();

        if ($afetadas === 0) {
            responder(false, "Disciplina não encontrada.");
        }

        responder(true, "Disciplina excluída com sucesso.");
    }

    responder(false, "Nenhuma ação válida foi informada.");
}

/* MÉTODO NÃO PERMITIDO */

http_response_code(405);
header("Allow: GET, POST");

responder(false, "Método não permitido.");

?>
