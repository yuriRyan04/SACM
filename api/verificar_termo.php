
<?php
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function responder(array $dados, int $status = 200): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }

    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

// Verifica se o login continua ativo.
if (empty($_SESSION['matricula']) || empty($_SESSION['tipo'])) {
    responder([
        'sucesso' => false,
        'login' => false,
        'erro' => 'Sessão não encontrada. Faça login novamente.'
    ], 401);
}

$tipo = strtolower(trim((string) $_SESSION['tipo']));
$matricula = (string) $_SESSION['matricula'];

$tabelas = [
    'aluno' => 'aluno',
    'professor' => 'professor',
    'gestao' => 'gestao'
];

if (!isset($tabelas[$tipo])) {
    responder([
        'sucesso' => false,
        'erro' => 'Perfil de usuário inválido.'
    ], 403);
}

$versaoAtual = '1.0';

try {
    require_once __DIR__ . '/conexao.php';

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new RuntimeException(
            'A conexão não disponibilizou a variável $mysqli.'
        );
    }

    $tabela = $tabelas[$tipo];

    $sql = "SELECT termo_aceito, termo_versao
            FROM `$tabela`
            WHERE matricula = ?
            LIMIT 1";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Não foi possível preparar a consulta do termo.'
        );
    }

    $stmt->bind_param('s', $matricula);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();

    $stmt->close();

    if (!$usuario) {
        responder([
            'sucesso' => false,
            'erro' => 'Usuário não encontrado na tabela ' . $tabela . '.'
        ], 404);
    }

    $aceito = (
        (int) ($usuario['termo_aceito'] ?? 0) === 1 &&
        (string) ($usuario['termo_versao'] ?? '') === $versaoAtual
    );

    responder([
        'sucesso' => true,
        'login' => true,
        'tipo' => $tipo,
        'matricula' => $matricula,
        'aceito' => $aceito,
        'versao' => $versaoAtual
    ]);

} catch (Throwable $e) {
    error_log(
        'SACM verificar_termo.php: ' . $e->getMessage()
    );

    responder([
        'sucesso' => false,
        'erro' => 'Falha ao verificar o termo. Confira o log do PHP.'
    ], 500);
}
?>
