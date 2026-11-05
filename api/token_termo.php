
<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function responder(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['matricula']) || empty($_SESSION['tipo'])) {
    responder([
        'sucesso' => false,
        'erro' => 'Sua sessão expirou. Faça login novamente.'
    ], 401);
}

if (empty($_SESSION['token_termo'])) {
    $_SESSION['token_termo'] = bin2hex(random_bytes(32));
}

responder([
    'sucesso' => true,
    'token' => $_SESSION['token_termo']
]);
?>