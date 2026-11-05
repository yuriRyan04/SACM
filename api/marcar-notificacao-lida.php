
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/conexao.php';

function responder($sucesso, $mensagem, $codigo = 200)
{
    http_response_code($codigo);

    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    error_log('[SACM] Conexão indisponível ao marcar notificação.');
    responder(false, 'Erro de conexão com o banco.', 500);
}

if (
    empty($_SESSION['matricula']) ||
    !isset($_SESSION['tipo']) ||
    $_SESSION['tipo'] !== 'aluno'
) {
    responder(false, 'Sessão não autenticada.', 401);
}

$dados = json_decode(file_get_contents('php://input'), true);

$id = filter_var(
    $dados['id_notificacao'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    responder(false, 'Notificação inválida.', 400);
}

$matricula = (string) $_SESSION['matricula'];

$sql = "
    UPDATE notificacoes n
    INNER JOIN monitores m
        ON m.id_monitor = n.id_monitor
    SET n.lida = 1
    WHERE n.id_notificacao = ?
      AND m.matricula = ?
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log('[SACM] Erro ao preparar atualização: ' . $mysqli->error);
    responder(false, 'Erro ao preparar atualização.', 500);
}

$stmt->bind_param('is', $id, $matricula);

if (!$stmt->execute()) {
    error_log('[SACM] Erro ao marcar notificação: ' . $stmt->error);
    $stmt->close();

    responder(false, 'Erro ao atualizar notificação.', 500);
}

$stmt->close();

responder(true, 'Notificação atualizada.');
?>
