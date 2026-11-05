
<?php

session_start();

include("conexao.php");

header("Content-Type: application/json; charset=UTF-8");

// ==========================================
// VERIFICAR LOGIN
// ==========================================

if (!isset($_SESSION["matricula"]) || !isset($_SESSION["tipo"])) {

    echo json_encode([
        "sucesso" => false,
        "codigo" => "nao_logado"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$matricula = $_SESSION["matricula"];
$tipo = $_SESSION["tipo"];

// ==========================================
// ALUNO
// ==========================================

if ($tipo === "aluno") {

    $sql = "SELECT id_aluno, Nome, matricula, serie, turma, curso, foto
            FROM aluno
            WHERE matricula = ?";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        echo json_encode([
            "sucesso" => false,
            "codigo" => "erro_banco"
        ]);

        exit;
    }

    $stmt->bind_param("s", $matricula);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows !== 1) {

        echo json_encode([
            "sucesso" => false,
            "codigo" => "usuario_nao_encontrado"
        ]);

        $stmt->close();
        exit;
    }

    $usuario = $resultado->fetch_assoc();

    echo json_encode([
        "sucesso" => true,
        "tipo" => "aluno",
        "id_aluno" => $usuario["id_aluno"],
        "nome" => $usuario["Nome"],
        "matricula" => $usuario["matricula"],
        "serie" => $usuario["serie"],
        "turma" => $usuario["turma"],
        "curso" => $usuario["curso"],
        "disciplina" => "",
        "foto" => $usuario["foto"]
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();
    exit;
}

// ==========================================
// PROFESSOR
// ==========================================

if ($tipo === "professor") {

    $sql = "SELECT id_professor, nome, matricula, foto, id_disciplinas
            FROM professor
            WHERE matricula = ?";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        echo json_encode([
            "sucesso" => false,
            "codigo" => "erro_banco"
        ]);

        exit;
    }

    $stmt->bind_param("s", $matricula);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows !== 1) {

        echo json_encode([
            "sucesso" => false,
            "codigo" => "usuario_nao_encontrado"
        ]);

        $stmt->close();
        exit;
    }

    $usuario = $resultado->fetch_assoc();

    echo json_encode([
        "sucesso" => true,
        "tipo" => "professor",
        "id_professor" => $usuario["id_professor"],
        "nome" => $usuario["nome"],
        "matricula" => $usuario["matricula"],
        "turma" => "",
        "id_disciplinas" => $usuario["id_disciplinas"],
        "foto" => $usuario["foto"]
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();
    exit;
}

// ==========================================
// GESTÃO
// ==========================================

if ($tipo === "gestao") {

    $sql = "SELECT id_gestao, nome, matricula, foto
            FROM gestao
            WHERE matricula = ?";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        echo json_encode([
            "sucesso" => false,
            "codigo" => "erro_banco"
        ]);

        exit;
    }

    $stmt->bind_param("s", $matricula);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows !== 1) {

        echo json_encode([
            "sucesso" => false,
            "codigo" => "usuario_nao_encontrado"
        ]);

        $stmt->close();
        exit;
    }

    $usuario = $resultado->fetch_assoc();

    echo json_encode([
        "sucesso" => true,
        "tipo" => "gestao",
        "id_gestao" => $usuario["id_gestao"],
        "nome" => $usuario["nome"],
        "matricula" => $usuario["matricula"],
        "turma" => "",
        "disciplina" => "",
        "foto" => $usuario["foto"]
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();
    exit;
}

// ==========================================
// TIPO INVÁLIDO
// ==========================================

echo json_encode([
    "sucesso" => false,
    "codigo" => "tipo_invalido"
], JSON_UNESCAPED_UNICODE);

?>
