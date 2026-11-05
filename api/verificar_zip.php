
<?php

header("Content-Type: application/json; charset=UTF-8");

$caminhoFPDF = __DIR__ . "/lib/fpdf19/fpdf.php";

$resultado = [
    "ziparchive" => class_exists("ZipArchive"),
    "fpdf" => file_exists($caminhoFPDF),
    "mensagem" => ""
];

if ($resultado["ziparchive"] && $resultado["fpdf"]) {
    $resultado["mensagem"] =
        "FPDF e ZipArchive estão disponíveis.";
} elseif (!$resultado["ziparchive"] && !$resultado["fpdf"]) {
    $resultado["mensagem"] =
        "FPDF não encontrado e ZipArchive indisponível.";
} elseif (!$resultado["ziparchive"]) {
    $resultado["mensagem"] =
        "FPDF encontrado, mas ZipArchive não está habilitado.";
} else {
    $resultado["mensagem"] =
        "ZipArchive disponível, mas FPDF não foi encontrado.";
}

echo json_encode(
    $resultado,
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
