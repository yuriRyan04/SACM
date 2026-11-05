
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/conexao.php';

function responder($sucesso, $dados = [], $codigo = 200)
{
    http_response_code($codigo);

    echo json_encode(
        array_merge(['sucesso' => $sucesso], $dados),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    error_log('[SACM] Conexão indisponível em notificacoes.php.');

    responder(false, [
        'mensagem' => 'Erro de conexão com o banco.'
    ], 500);
}

if (
    empty($_SESSION['matricula']) ||
    !isset($_SESSION['tipo']) ||
    $_SESSION['tipo'] !== 'aluno'
) {
    responder(false, [
        'mensagem' => 'Sessão de aluno não autenticada.'
    ], 401);
}

$matricula = (string) $_SESSION['matricula'];

$sql = "
    SELECT
        n.id_notificacao,
        n.id_agendamento,
        n.lida,
        n.criada_em,
        a.data_agendamento,
        a.hora_inicio,
        a.assunto,
        a.status,
        d.disciplina
    FROM notificacoes n
    INNER JOIN agendamentos a
        ON a.id_agendamento = n.id_agendamento
    INNER JOIN monitores m
        ON m.id_monitor = n.id_monitor
    LEFT JOIN disciplinas d
        ON d.id_disciplinas = a.id_disciplinas
    WHERE m.matricula = ?
    ORDER BY n.criada_em DESC
    LIMIT 50
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log(
        '[SACM] Erro ao preparar notificacoes.php: ' . $mysqli->error
    );

    responder(false, [
        'mensagem' => 'Erro ao preparar consulta de notificações.'
    ], 500);
}

$stmt->bind_param('s', $matricula);

if (!$stmt->execute()) {
    error_log(
        '[SACM] Erro ao executar notificacoes.php: ' . $stmt->error
    );

    $stmt->close();

    responder(false, [
        'mensagem' => 'Erro ao consultar notificações.'
    ], 500);
}

$resultado = $stmt->get_result();

if (!$resultado) {
    error_log(
        '[SACM] Erro ao obter notificações: ' . $stmt->error
    );

    $stmt->close();

    responder(false, [
        'mensagem' => 'Erro ao ler notificações.'
    ], 500);
}

$notificacoes = [];
$naoLidas = 0;

while ($linha = $resultado->fetch_assoc()) {
    $linha['id_notificacao'] = (int) $linha['id_notificacao'];
    $linha['id_agendamento'] = (int) $linha['id_agendamento'];
    $linha['lida'] = (int) $linha['lida'];

    if ($linha['lida'] === 0) {
        $naoLidas++;
    }

    $notificacoes[] = $linha;
}

$stmt->close();

responder(true, [
    'nao_lidas' => $naoLidas,
    'notificacoes' => $notificacoes
]);
?>
