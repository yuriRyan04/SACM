
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissao_monitor.php';

function responderCadastro(
    bool $sucesso,
    string $mensagem,
    int $status = 200
): void {
    http_response_code($status);
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

sacmExigirUsuario();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderCadastro(false, 'Método não permitido.', 405);
}

$dados = json_decode(file_get_contents('php://input'), true);

$matriculaAluno = trim((string)($dados['matricula'] ?? ''));
$idDisciplina = filter_var(
    $dados['disciplina_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($matriculaAluno === '' || !$idDisciplina || $idDisciplina <= 0) {
    responderCadastro(false, 'Informe a matrícula e a disciplina.', 400);
}

if (!sacmPodeAcessarDisciplina($mysqli, (int)$idDisciplina)) {
    responderCadastro(
        false,
        'Você não tem permissão para cadastrar monitores nesta disciplina.',
        403
    );
}

try {
    $mysqli->begin_transaction();

    // Busca e bloqueia o registro do aluno.
    $stmt = $mysqli->prepare(
        "SELECT id_aluno, matricula, serie, turma, curso
         FROM aluno
         WHERE matricula = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bind_param('s', $matriculaAluno);
    $stmt->execute();
    $aluno = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$aluno) {
        $mysqli->rollback();
        responderCadastro(false, 'Aluno não encontrado.', 404);
    }

    // Busca e bloqueia a disciplina.
    $stmt = $mysqli->prepare(
        "SELECT id_disciplinas, disciplina, limite_monitores
         FROM disciplinas
         WHERE id_disciplinas = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bind_param('i', $idDisciplina);
    $stmt->execute();
    $disciplina = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$disciplina) {
        $mysqli->rollback();
        responderCadastro(false, 'Disciplina não encontrada.', 404);
    }

    // Evita duplicar a mesma disciplina para o aluno.
    $stmt = $mysqli->prepare(
        "SELECT COUNT(*) AS total
         FROM monitores
         WHERE matricula = ?
           AND CAST(id_disciplinas AS UNSIGNED) = ?"
    );

    $stmt->bind_param('si', $matriculaAluno, $idDisciplina);
    $stmt->execute();
    $duplicado = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($duplicado > 0) {
        $mysqli->rollback();
        responderCadastro(
            false,
            'Este aluno já é monitor desta disciplina.',
            409
        );
    }

    // Limite máximo de duas disciplinas por aluno.
    $idAluno = (int)$aluno['id_aluno'];

    $stmt = $mysqli->prepare(
        "SELECT COUNT(DISTINCT CAST(id_disciplinas AS UNSIGNED)) AS total
         FROM monitores
         WHERE id_aluno = ?"
    );

    $stmt->bind_param('i', $idAluno);
    $stmt->execute();
    $totalMonitorias = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($totalMonitorias >= 2) {
        $mysqli->rollback();
        responderCadastro(
            false,
            'Este aluno já atingiu o limite de duas monitorias.',
            409
        );
    }

    // Conta os monitores da mesma disciplina, série, turma e curso.
    $serie = (string)($aluno['serie'] ?? '');
    $turma = (string)($aluno['turma'] ?? '');
    $curso = (string)($aluno['curso'] ?? '');

    $stmt = $mysqli->prepare(
        "SELECT COUNT(*) AS total
         FROM monitores m
         INNER JOIN aluno a ON a.id_aluno = m.id_aluno
         WHERE CAST(m.id_disciplinas AS UNSIGNED) = ?
           AND a.serie = ?
           AND a.turma = ?
           AND a.curso = ?"
    );

    $stmt->bind_param(
        'isss',
        $idDisciplina,
        $serie,
        $turma,
        $curso
    );

    $stmt->execute();
    $totalNaTurma = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $limite = (int)($disciplina['limite_monitores'] ?? 0);

    if ($limite > 0 && $totalNaTurma >= $limite) {
        $mysqli->rollback();
        responderCadastro(
            false,
            'O limite de monitores desta disciplina para a turma foi atingido.',
            409
        );
    }

    // Cadastra o monitor.
    $stmt = $mysqli->prepare(
        "INSERT INTO monitores (matricula, id_disciplinas, id_aluno)
         VALUES (?, ?, ?)"
    );

    $idDisciplinaTexto = (string)$idDisciplina;

    $stmt->bind_param(
        'ssi',
        $matriculaAluno,
        $idDisciplinaTexto,
        $idAluno
    );

    $stmt->execute();
    $stmt->close();

    $mysqli->commit();

    responderCadastro(true, 'Monitor cadastrado com sucesso.');
} catch (Throwable $e) {
    try {
        $mysqli->rollback();
    } catch (Throwable $ignorado) {
    }

    responderCadastro(
        false,
        'Ocorreu um erro ao cadastrar o monitor. Verifique os dados e tente novamente.',
        500
    );
}
?>