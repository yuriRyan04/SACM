
<?php

ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ==========================================================
   RESPOSTA JSON
========================================================== */

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

/* ==========================================================
   VALIDAR MÉTODO HTTP
========================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder([
        'sucesso' => false,
        'erro' => 'Método não permitido.'
    ], 405);
}

/* ==========================================================
   VALIDAR SESSÃO
========================================================== */

if (
    empty($_SESSION['matricula']) ||
    empty($_SESSION['tipo'])
) {
    responder([
        'sucesso' => false,
        'erro' => 'Sua sessão expirou. Faça login novamente.'
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
        'erro' => 'Perfil inválido.'
    ], 403);
}

/* ==========================================================
   VALIDAR DADOS ENVIADOS
========================================================== */

$corpo = file_get_contents('php://input');
$entrada = json_decode($corpo, true);

if (
    !is_array($entrada) ||
    ($entrada['aceite'] ?? null) !== true
) {
    responder([
        'sucesso' => false,
        'erro' => 'Marque a caixa de confirmação antes de continuar.'
    ], 400);
}

/* ==========================================================
   VALIDAR TOKEN CSRF
========================================================== */

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$tokenSessao = $_SESSION['token_termo'] ?? '';

if (
    !is_string($token) ||
    !is_string($tokenSessao) ||
    $tokenSessao === '' ||
    !hash_equals($tokenSessao, $token)
) {
    responder([
        'sucesso' => false,
        'erro' => 'Token inválido. Atualize a página e tente novamente.'
    ], 403);
}

/* ==========================================================
   REGISTRAR ACEITE NO BANCO
========================================================== */

$versao = '1.0';

try {
    require_once __DIR__ . '/conexao.php';

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new RuntimeException(
            'Conexão MySQL indisponível.'
        );
    }

    $tabela = $tabelas[$tipo];

    $sql = "UPDATE `$tabela`
            SET termo_aceito = 1,
                termo_versao = ?,
                termo_aceito_em = NOW()
            WHERE matricula = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $versao, $matricula);
    $stmt->execute();
    $stmt->close();

    /* ======================================================
       CONFIRMAR QUE O ACEITE FOI SALVO
    ====================================================== */

    $consulta = $mysqli->prepare(
        "SELECT termo_aceito, termo_versao
         FROM `$tabela`
         WHERE matricula = ?
         LIMIT 1"
    );

    $consulta->bind_param('s', $matricula);
    $consulta->execute();

    $usuario = $consulta->get_result()->fetch_assoc();

    $consulta->close();

    if (
        !$usuario ||
        (int) $usuario['termo_aceito'] !== 1 ||
        (string) $usuario['termo_versao'] !== $versao
    ) {
        throw new RuntimeException(
            'O banco não confirmou o aceite.'
        );
    }

    /* ======================================================
       LIMPAR TOKEN UTILIZADO
    ====================================================== */

    unset($_SESSION['token_termo']);

    /* ======================================================
       RESPOSTA DE SUCESSO
       O PERFIL SERÁ USADO NO REDIRECIONAMENTO
    ====================================================== */

    responder([
        'sucesso' => true,
        'mensagem' => 'Termo registrado com sucesso.',
        'versao' => $versao,
        'tipo' => $tipo
    ]);

} catch (Throwable $e) {
    error_log(
        'SACM aceitar_termo.php: ' . $e->getMessage()
    );

    responder([
        'sucesso' => false,
        'erro' => 'Não foi possível salvar a autorização. Confira o log do PHP.'
    ], 500);
}
?>
