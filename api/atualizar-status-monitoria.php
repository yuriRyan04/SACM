
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';

function responder($sucesso, $mensagem) {
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(false, 'Método não permitido.');
}

$dados = json_decode(file_get_contents('php://input'), true);

$idAgendamento = filter_var(
    $dados['id_agendamento'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$idAgendamento || $idAgendamento < 1) {
    http_response_code(400);
    responder(false, 'Agendamento inválido.');
}

try {
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new Exception('Conexão com o banco indisponível.');
    }

    $mysqli->set_charset('utf8mb4');

    $sql = "UPDATE agendamentos
            SET status = 'realizado'
            WHERE id_agendamento = ?
              AND status = 'agendado'";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new Exception('Não foi possível preparar a atualização.');
    }

    $stmt->bind_param('i', $idAgendamento);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        responder(true, 'Monitoria marcada como realizada!');
    }

    // Verifica se o registro existe e já estava realizado.
    $stmt->close();

    $consulta = $mysqli->prepare(
        "SELECT status FROM agendamentos WHERE id_agendamento = ?"
    );
    $consulta->bind_param('i', $idAgendamento);
    $consulta->execute();

    $resultado = $consulta->get_result()->fetch_assoc();

    if (!$resultado) {
        http_response_code(404);
        responder(false, 'Agendamento não encontrado.');
    }

    if (strtolower($resultado['status']) === 'realizado') {
        responder(true, 'Este agendamento já está realizado.');
    }

    responder(false, 'Não foi possível atualizar o status.');

} catch (Throwable $e) {
    error_log('Erro ao atualizar monitoria: ' . $e->getMessage());
    http_response_code(500);
    responder(false, 'Erro interno ao atualizar a monitoria.');
}
