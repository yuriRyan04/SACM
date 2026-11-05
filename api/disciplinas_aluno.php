
<?php
/*
 * SACM — DISCIPLINAS DO ALUNO
 *
 * Mostra somente disciplinas compatíveis com a série e o curso
 * do aluno autenticado.
 *
 * Regras:
 * - "Todas": permite qualquer série.
 * - "Todos": permite qualquer curso.
 * - Aceita variações de escrita da série e acentuação.
 * - Não confia em matrícula, série ou curso enviados pela URL.
 */

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once __DIR__ . "/conexao.php";

/* =====================================================
   RESPOSTA JSON
===================================================== */

function responder($dados, $status = 200)
{
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/* =====================================================
   NORMALIZAR TEXTO
===================================================== */

function normalizarTexto($texto)
{
    $texto = trim((string) $texto);

    if (function_exists("iconv")) {
        $convertido = @iconv(
            "UTF-8",
            "ASCII//TRANSLIT//IGNORE",
            $texto
        );

        if ($convertido !== false) {
            $texto = $convertido;
        }
    }

    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    $texto = trim(preg_replace('/\s+/', ' ', $texto));

    /*
     * Padroniza:
     * 3º
     * 3ª
     * 3a
     * 3o
     * 3ª série
     * 3º série
     * 3 serie
     *
     * Todos passam a ser "3 serie".
     */

    if (
        preg_match(
            '/^([1-3])\s*(?:o|a)?(?:\s+serie)?$/',
            $texto,
            $correspondencia
        )
    ) {
        return $correspondencia[1] . " serie";
    }

    return $texto;
}

/* =====================================================
   DIVIDIR OPÇÕES CADASTRADAS
===================================================== */

function separarOpcoes($valor)
{
    if ($valor === null || trim((string) $valor) === "") {
        return [];
    }

    $valor = trim((string) $valor);

    // Também aceita arrays armazenados em JSON.
    $json = json_decode($valor, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        $opcoes = $json;
    } else {
        $opcoes = preg_split('/[,;|]+/', $valor);
    }

    $resultado = [];

    foreach ($opcoes as $opcao) {
        if (is_string($opcao) || is_numeric($opcao)) {
            $opcao = trim((string) $opcao);

            if ($opcao !== "") {
                $resultado[] = $opcao;
            }
        }
    }

    return $resultado;
}

/* =====================================================
   COMPARAR SÉRIE OU CURSO
===================================================== */

function correspondeOpcao(
    $opcoesCadastradas,
    $valorAluno,
    $liberacaoTotal
) {
    $valorAlunoNormalizado = normalizarTexto($valorAluno);

    // Não libera disciplinas específicas se o dado estiver vazio.
    if ($valorAlunoNormalizado === "") {
        return false;
    }

    $opcoes = separarOpcoes($opcoesCadastradas);

    foreach ($opcoes as $opcao) {
        $opcaoNormalizada = normalizarTexto($opcao);

        // "Todas" e "Todos".
        if (
            $opcaoNormalizada === normalizarTexto($liberacaoTotal)
        ) {
            return true;
        }

        // Comparação normalizada.
        if (
            $opcaoNormalizada !== "" &&
            $opcaoNormalizada === $valorAlunoNormalizado
        ) {
            return true;
        }

        /*
         * Compatibilidade adicional de séries:
         * aceita "3", "3º", "3ª série" e "3 serie".
         */

        $serieOpcao = null;
        $serieAluno = null;

        if (
            preg_match(
                '/^([1-3])(?:\s+serie)?$/',
                $opcaoNormalizada,
                $m1
            )
        ) {
            $serieOpcao = $m1[1];
        }

        if (
            preg_match(
                '/^([1-3])(?:\s+serie)?$/',
                $valorAlunoNormalizado,
                $m2
            )
        ) {
            $serieAluno = $m2[1];
        }

        if (
            $serieOpcao !== null &&
            $serieAluno !== null &&
            $serieOpcao === $serieAluno
        ) {
            return true;
        }
    }

    return false;
}

/* =====================================================
   1. VALIDAR CONEXÃO
===================================================== */

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    responder([
        "sucesso" => false,
        "mensagem" => "A conexão com o banco de dados não está disponível."
    ], 500);
}

/* =====================================================
   2. VALIDAR LOGIN DO ALUNO
===================================================== */

if (
    !isset($_SESSION["tipo"]) ||
    $_SESSION["tipo"] !== "aluno" ||
    empty($_SESSION["matricula"])
) {
    responder([
        "sucesso" => false,
        "mensagem" => "Faça login como aluno para visualizar as disciplinas."
    ], 401);
}

$matricula = trim((string) $_SESSION["matricula"]);

/* =====================================================
   3. CONSULTAR SÉRIE E CURSO DO ALUNO
===================================================== */

$sqlAluno = "
    SELECT serie, curso
    FROM aluno
    WHERE matricula = ?
    LIMIT 1
";

$stmtAluno = $mysqli->prepare($sqlAluno);

if (!$stmtAluno) {
    responder([
        "sucesso" => false,
        "mensagem" => "Não foi possível preparar a consulta do aluno."
    ], 500);
}

$stmtAluno->bind_param("s", $matricula);

if (!$stmtAluno->execute()) {
    $stmtAluno->close();

    responder([
        "sucesso" => false,
        "mensagem" => "Não foi possível consultar os dados do aluno."
    ], 500);
}

$resultadoAluno = $stmtAluno->get_result();
$aluno = $resultadoAluno->fetch_assoc();

$stmtAluno->close();

if (!$aluno) {
    responder([
        "sucesso" => false,
        "mensagem" => "O aluno da sessão não foi encontrado no banco de dados."
    ], 404);
}

$serieAluno = trim((string) ($aluno["serie"] ?? ""));
$cursoAluno = trim((string) ($aluno["curso"] ?? ""));

if ($serieAluno === "" || $cursoAluno === "") {
    responder([
        "sucesso" => false,
        "mensagem" => "A série ou o curso está vazio no cadastro do aluno."
    ], 422);
}

/* =====================================================
   4. VALIDAR ÁREA SOLICITADA
===================================================== */

$area = trim((string) ($_GET["area"] ?? ""));

$areasPermitidas = [
    "LINGUAGENS",
    "NATUREZA",
    "HUMANAS",
    "MATEMÁTICA",
    "Base Técnica / INFORMÁTICA",
    "Base Técnica / REDES"
];

if ($area !== "") {
    $areaEncontrada = false;

    foreach ($areasPermitidas as $areaPermitida) {
        if (
            normalizarTexto($area) ===
            normalizarTexto($areaPermitida)
        ) {
            $area = $areaPermitida;
            $areaEncontrada = true;
            break;
        }
    }

    if (!$areaEncontrada) {
        responder([
            "sucesso" => false,
            "mensagem" => "A área informada é inválida."
        ], 400);
    }
}

/* =====================================================
   5. CONSULTAR DISCIPLINAS
===================================================== */

$sqlDisciplinas = "
    SELECT
        id_disciplinas,
        disciplina,
        area,
        series,
        cursos
    FROM disciplinas
";

$stmtDisciplinas = null;

if ($area !== "") {
    $sqlDisciplinas .= "
        WHERE area = ?
        ORDER BY disciplina ASC
    ";

    $stmtDisciplinas = $mysqli->prepare($sqlDisciplinas);

    if (!$stmtDisciplinas) {
        responder([
            "sucesso" => false,
            "mensagem" => "Não foi possível preparar a consulta das disciplinas."
        ], 500);
    }

    $stmtDisciplinas->bind_param("s", $area);

    if (!$stmtDisciplinas->execute()) {
        $stmtDisciplinas->close();

        responder([
            "sucesso" => false,
            "mensagem" => "Erro ao consultar as disciplinas."
        ], 500);
    }

    $resultadoDisciplinas = $stmtDisciplinas->get_result();
} else {
    $sqlDisciplinas .= " ORDER BY disciplina ASC";

    $resultadoDisciplinas = $mysqli->query($sqlDisciplinas);

    if (!$resultadoDisciplinas) {
        responder([
            "sucesso" => false,
            "mensagem" => "Erro ao consultar as disciplinas."
        ], 500);
    }
}

/* =====================================================
   6. FILTRAR POR SÉRIE E CURSO
===================================================== */

$disciplinasDisponiveis = [];

while ($disciplina = $resultadoDisciplinas->fetch_assoc()) {
    $seriesCadastradas = $disciplina["series"] ?? "";
    $cursosCadastrados = $disciplina["cursos"] ?? "";

    $seriePermitida = correspondeOpcao(
        $seriesCadastradas,
        $serieAluno,
        "Todas"
    );

    $cursoPermitido = correspondeOpcao(
        $cursosCadastrados,
        $cursoAluno,
        "Todos"
    );

    // A série E o curso devem ser compatíveis.
    if (!$seriePermitida || !$cursoPermitido) {
        continue;
    }

    $disciplinasDisponiveis[] = [
        "id" => (int) $disciplina["id_disciplinas"],
        "id_disciplinas" => (int) $disciplina["id_disciplinas"],
        "nome" => $disciplina["disciplina"],
        "disciplina" => $disciplina["disciplina"],
        "area" => $disciplina["area"],
        "series" => $disciplina["series"],
        "cursos" => $disciplina["cursos"]
    ];
}

/* =====================================================
   7. ENCERRAR CONSULTA
===================================================== */

if ($stmtDisciplinas instanceof mysqli_stmt) {
    $stmtDisciplinas->close();
}

/* =====================================================
   8. DEVOLVER JSON
===================================================== */

responder($disciplinasDisponiveis);
?>