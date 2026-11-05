
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

// ==========================================
// RESPOSTA JSON
// ==========================================

function responder($sucesso, $mensagem, $foto = null) {
    echo json_encode([
        "sucesso" => $sucesso,
        "mensagem" => $mensagem,
        "foto" => $foto
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ==========================================
// IDENTIFICAR USUÁRIO LOGADO
// ==========================================

$matricula = $_SESSION["matricula"] ?? "";
$tipo = strtolower(trim($_SESSION["tipo"] ?? ""));

$tabelasPermitidas = [
    "aluno" => "aluno",
    "professor" => "professor",
    "gestao" => "gestao"
];

if (empty($matricula) || !isset($tabelasPermitidas[$tipo])) {
    responder(false, "Sessão inválida. Entre novamente no SACM.");
}

$tabela = $tabelasPermitidas[$tipo];

// ==========================================
// VALIDAR ARQUIVO
// ==========================================

if (!isset($_FILES["foto"])) {
    responder(false, "Selecione uma foto para enviar.");
}

$arquivo = $_FILES["foto"];

if ($arquivo["error"] !== UPLOAD_ERR_OK) {
    responder(false, "Falha no envio da foto. Código: " . $arquivo["error"]);
}

if (!is_uploaded_file($arquivo["tmp_name"])) {
    responder(false, "Arquivo enviado inválido.");
}

if ($arquivo["size"] <= 0 || $arquivo["size"] > 5 * 1024 * 1024) {
    responder(false, "A foto deve ter até 5 MB.");
}

// ==========================================
// VALIDAR FORMATO REAL DA IMAGEM
// ==========================================

$imagem = @getimagesize($arquivo["tmp_name"]);

if ($imagem === false) {
    responder(false, "O arquivo enviado não é uma imagem válida.");
}

$tipoMime = (new finfo(FILEINFO_MIME_TYPE))
    ->file($arquivo["tmp_name"]);

$formatos = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp"
];

if (!isset($formatos[$tipoMime])) {
    responder(false, "Use uma imagem JPG, PNG ou WEBP.");
}

$extensao = $formatos[$tipoMime];

// ==========================================
// LOCALIZAR USUÁRIO
// ==========================================

$sql = "SELECT matricula, foto
        FROM `$tabela`
        WHERE matricula = ?
        LIMIT 1";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    responder(false, "Não foi possível consultar o usuário.");
}

$stmt->bind_param("s", $matricula);
$stmt->execute();

$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

$stmt->close();

if (!$usuario) {
    responder(false, "Usuário não encontrado na tabela " . $tabela . ".");
}

// ==========================================
// PREPARAR PASTA DE FOTOS
// ==========================================

$pasta = dirname(__DIR__) . "/img/alunos/";

if (!is_dir($pasta)) {
    if (!mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        responder(false, "Não foi possível criar a pasta de fotos.");
    }
}

if (!is_writable($pasta)) {
    responder(false, "A pasta de fotos não permite gravação.");
}

// ==========================================
// NOME ÚNICO POR USUÁRIO E TIPO
// ==========================================

// A mesma pessoa sempre substitui a própria foto.
// O nome não depende da extensão anterior.

$identificador = hash("sha256", $tipo . "|" . $matricula);

$nomeArquivo = $identificador . "." . $extensao;

$caminhoCompleto = $pasta . $nomeArquivo;
$caminhoBanco = "img/alunos/" . $nomeArquivo;

// ==========================================
// SALVAR NOVA FOTO
// ==========================================

// Não apagamos a foto anterior antes de salvar a nova.

$temporario = $pasta . $identificador . ".tmp";

if (!move_uploaded_file($arquivo["tmp_name"], $temporario)) {
    responder(false, "Não foi possível salvar a nova foto.");
}

// Troca a extensão do arquivo temporário pelo formato validado.

if (file_exists($caminhoCompleto)) {
    if (!unlink($caminhoCompleto)) {
        @unlink($temporario);
        responder(false, "Não foi possível substituir a foto atual.");
    }
}

if (!rename($temporario, $caminhoCompleto)) {
    @unlink($temporario);
    responder(false, "Não foi possível finalizar o salvamento.");
}

// ==========================================
// ATUALIZAR FOTO NO BANCO
// ==========================================

$sql = "UPDATE `$tabela`
        SET foto = ?
        WHERE matricula = ?";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    responder(false, "Não foi possível atualizar a foto no banco.");
}

$stmt->bind_param("ss", $caminhoBanco, $matricula);

if (!$stmt->execute()) {
    $stmt->close();
    responder(false, "Erro ao atualizar a foto no banco de dados.");
}

$stmt->close();

// ==========================================
// SUCESSO
// ==========================================

echo json_encode([
    "sucesso" => true,
    "mensagem" => "Foto de perfil atualizada com sucesso!",
    "foto" => "./" . $caminhoBanco . "?v=" . time()
], JSON_UNESCAPED_UNICODE);

?>
