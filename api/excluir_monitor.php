
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissao_monitor.php';

sacmExigirUsuario();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sacmResponderPermissao(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$dados = json_decode(file_get_contents('php://input'), true);

$idMonitor = filter_var(
    $dados['id_monitor'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$idMonitor || $idMonitor <= 0) {
    sacmResponderPermissao(
        false,
        'Informe um monitor válido.',
        [],
        400
    );
}

try {
    $stmt = $mysqli->prepare(
        "SELECT CAST(id_disciplinas AS UNSIGNED) AS id_disciplina
         FROM monitores
         WHERE id_monitor = ?
         LIMIT 1"
    );

    $stmt->bind_param('i', $idMonitor);
    $stmt->execute();
    $monitor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$monitor) {
        sacmResponderPermissao(
            false,
            'Monitor não encontrado.',
            [],
            404
        );
    }

    $idDisciplina = (int)$monitor['id_disciplina'];

    sacmExigirDisciplina($mysqli, $idDisciplina);

    $stmt = $mysqli->prepare(
        "DELETE FROM monitores WHERE id_monitor = ?"
    );

    $stmt->bind_param('i', $idMonitor);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Monitor excluído com sucesso.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    sacmResponderPermissao(
        false,
        'Não foi possível excluir o monitor.',
        [],
        500
    );
}
?>