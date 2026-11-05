
<?php

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

$areasPermitidas = [
    "LINGUAGENS",
    "NATUREZA",
    "HUMANAS",
    "MATEMÁTICA",
    "Base Técnica / INFORMÁTICA",
    "Base Técnica / REDES"
];

function responder($sucesso, $mensagem = "", $dados = null)
{
    $resposta = ["sucesso" => $sucesso];

    if ($mensagem !== "") {
        $resposta["mensagem"] = $mensagem;
    }

    if ($dados !== null) {
        $resposta = array_merge($resposta, $dados);
    }

    echo json_encode(
        $resposta,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// Verificar conexão
if (!isset($mysqli) || !$mysqli || $mysqli->connect_error) {
    responder(false, "Erro na conexão com o banco de dados.");
}

$metodo = $_SERVER["REQUEST_METHOD"];

// ==================================================
// GET: BUSCAR UMA DISCIPLINA PELO ID
// GET: LISTAR TODAS OU FILTRAR POR ÁREA
// ==================================================

if ($metodo === "GET") {

    // Verificar se o parâmetro id foi enviado
    if (isset($_GET["id"])) {

        $idRecebido = $_GET["id"];

        // Validar o ID recebido
        if (
            !is_string($idRecebido) ||
            filter_var($idRecebido, FILTER_VALIDATE_INT) === false ||
            (int) $idRecebido <= 0
        ) {
            responder(false, "ID da disciplina inválido.");
        }

        $id = (int) $idRecebido;

        $sql = "
            SELECT id_disciplinas, disciplina, area
            FROM disciplinas
            WHERE id_disciplinas = ?
        ";

        $stmt = $mysqli->prepare($sql);

        if (!$stmt) {
            responder(false, "Erro ao preparar consulta.");
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            $stmt->close();
            responder(false, "Erro ao consultar disciplina.");
        }

        $resultado = $stmt->get_result();
        $linha = $resultado->fetch_assoc();

        $stmt->close();

        if (!$linha) {
            responder(false, "Disciplina não encontrada.");
        }

        responder(true, "", [
            "id" => (int) $linha["id_disciplinas"],
            "id_disciplinas" => (int) $linha["id_disciplinas"],
            "nome" => $linha["disciplina"],
            "disciplina" => $linha["disciplina"],
            "area" => $linha["area"]
        ]);
    }

    // Listar disciplinas ou filtrar por área
    $area = isset($_GET["area"])
        ? trim($_GET["area"])
        : "";

    if (
        $area !== "" &&
        !in_array($area, $areasPermitidas, true)
    ) {
        responder(false, "Área inválida.");
    }

    if ($area !== "") {

        $sql = "
            SELECT id_disciplinas, disciplina, area
            FROM disciplinas
            WHERE area = ?
            ORDER BY disciplina ASC
        ";

        $stmt = $mysqli->prepare($sql);

        if (!$stmt) {
            responder(false, "Erro ao preparar consulta.");
        }

        $stmt->bind_param("s", $area);

        if (!$stmt->execute()) {
            $stmt->close();
            responder(false, "Erro ao listar disciplinas.");
        }

        $resultado = $stmt->get_result();

    } else {

        $sql = "
            SELECT id_disciplinas, disciplina, area
            FROM disciplinas
            ORDER BY area ASC, disciplina ASC
        ";

        $resultado = $mysqli->query($sql);

        if (!$resultado) {
            responder(false, "Erro ao listar disciplinas.");
        }
    }

    $lista = [];

    while ($linha = $resultado->fetch_assoc()) {

        $lista[] = [
            "id" => (int) $linha["id_disciplinas"],
            "id_disciplinas" => (int) $linha["id_disciplinas"],
            "nome" => $linha["disciplina"],
            "disciplina" => $linha["disciplina"],
            "area" => $linha["area"]
        ];
    }

    if (isset($stmt)) {
        $stmt->close();
    }

    echo json_encode($lista, JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================================================
// POST: CADASTRAR OU EXCLUIR DISCIPLINA
// ==================================================

if ($metodo === "POST") {

    // CADASTRAR DISCIPLINA
    if (isset($_POST["adicionar"])) {

        $nome = trim($_POST["nome"] ?? "");
        $area = trim($_POST["area"] ?? "");

        if ($nome === "") {
            responder(false, "Digite o nome da disciplina.");
        }

        if (!in_array($area, $areasPermitidas, true)) {
            responder(false, "Área inválida.");
        }

        $stmt = $mysqli->prepare("
            SELECT id_disciplinas
            FROM disciplinas
            WHERE disciplina = ? AND area = ?
        ");

        if (!$stmt) {
            responder(false, "Erro ao verificar disciplina.");
        }

        $stmt->bind_param("ss", $nome, $area);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();

            responder(
                false,
                "Essa disciplina já está cadastrada nessa área."
            );
        }

        $stmt->close();

        $stmt = $mysqli->prepare("
            INSERT INTO disciplinas (disciplina, area)
            VALUES (?, ?)
        ");

        if (!$stmt) {
            responder(false, "Erro ao preparar cadastro.");
        }

        $stmt->bind_param("ss", $nome, $area);

        if (!$stmt->execute()) {
            $stmt->close();
            responder(false, "Erro ao cadastrar disciplina.");
        }

        $idNovo = $stmt->insert_id;
        $stmt->close();

        responder(true, "Disciplina cadastrada com sucesso!", [
            "id" => (int) $idNovo,
            "id_disciplinas" => (int) $idNovo,
            "nome" => $nome,
            "disciplina" => $nome,
            "area" => $area
        ]);
    }

    // EXCLUIR DISCIPLINA
    if (isset($_POST["excluir"])) {

        $idRecebido = $_POST["excluir"];

        if (
            !is_string($idRecebido) ||
            filter_var($idRecebido, FILTER_VALIDATE_INT) === false ||
            (int) $idRecebido <= 0
        ) {
            responder(false, "ID inválido.");
        }

        $id = (int) $idRecebido;

        $stmt = $mysqli->prepare("
            DELETE FROM disciplinas
            WHERE id_disciplinas = ?
        ");

        if (!$stmt) {
            responder(false, "Erro ao preparar exclusão.");
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            $stmt->close();
            responder(false, "Erro ao excluir disciplina.");
        }

        $afetadas = $stmt->affected_rows;
        $stmt->close();

        if ($afetadas === 0) {
            responder(false, "Disciplina não encontrada.");
        }

        responder(true, "Disciplina excluída com sucesso.");
    }

    responder(false, "Nenhuma ação foi informada.");
}

responder(false, "Método não permitido.");

?>
