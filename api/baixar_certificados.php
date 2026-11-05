
<?php
/*
 * SACM — BAIXAR CERTIFICADOS EM ZIP
 * Arquivo: api/baixar_certificados.php
 *
 * Usa o mesmo modelo original do certificado individual.
 *
 * Requisitos:
 * - api/conexao.php
 * - api/permissao_monitor.php
 * - api/lib/fpdf19/fpdf.php
 * - Extensão ZipArchive habilitada no PHP
 */

session_start();

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/permissao_monitor.php";

sacmExigirUsuario();

if (!sacmEhGestao()) {
    http_response_code(403);
    exit("Acesso negado. Somente a Gestão pode emitir certificados.");
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    exit("Não foi possível conectar ao banco de dados.");
}

if (!class_exists("ZipArchive")) {
    http_response_code(500);
    exit("A extensão ZipArchive não está habilitada no servidor.");
}

$fpdfPath = __DIR__ . "/lib/fpdf19/fpdf.php";
$modelo = __DIR__ . "/../img/modelo-certificado-monitoria.png";

if (!is_file($fpdfPath)) {
    http_response_code(500);
    exit("A biblioteca FPDF não foi encontrada.");
}

if (!is_file($modelo)) {
    http_response_code(500);
    exit(
        "Modelo não encontrado. Verifique o arquivo " .
        "img/modelo-certificado-monitoria.png."
    );
}

require_once $fpdfPath;

/* Disciplina selecionada na página de Gestão. */
$idDisciplina = filter_input(
    INPUT_GET,
    "id_disciplinas",
    FILTER_VALIDATE_INT
);

if (!$idDisciplina || $idDisciplina < 1) {
    http_response_code(400);
    exit("Informe uma disciplina válida.");
}

/* Busca somente certificados que já foram salvos no banco. */
$sql = "
    SELECT
        cm.id_certificado,
        cm.id_monitor,
        cm.id_disciplinas,
        cm.carga_horaria,
        cm.periodo,
        cm.data_emissao,
        a.Nome AS nome_monitor,
        d.disciplina
    FROM certificados_monitoria cm
    INNER JOIN monitores m
        ON m.id_monitor = cm.id_monitor
    INNER JOIN aluno a
        ON a.id_aluno = m.id_aluno
    INNER JOIN disciplinas d
        ON d.id_disciplinas = cm.id_disciplinas
    WHERE cm.id_disciplinas = ?
    ORDER BY a.Nome ASC
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit("Não foi possível preparar a consulta dos certificados.");
}

$stmt->bind_param("i", $idDisciplina);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("Não foi possível consultar os certificados.");
}

$resultado = $stmt->get_result();
$certificados = [];

while ($linha = $resultado->fetch_assoc()) {
    $certificados[] = $linha;
}

$stmt->close();

if (!$certificados) {
    http_response_code(404);
    exit(
        "Não existem certificados salvos para esta disciplina. " .
        "Salve os dados dos monitores antes de baixar o ZIP."
    );
}

/* Conversão de texto para o FPDF clássico. */
function certificadoZipTextoPDF($texto)
{
    $texto = (string) $texto;

    if (function_exists("iconv")) {
        $convertido = @iconv(
            "UTF-8",
            "Windows-1252//TRANSLIT",
            $texto
        );

        if ($convertido !== false) {
            return $convertido;
        }
    }

    return utf8_decode($texto);
}

/* Cria nomes de arquivos seguros. */
function certificadoZipNomeArquivo($texto)
{
    $texto = (string) $texto;

    if (function_exists("iconv")) {
        $convertido = @iconv(
            "UTF-8",
            "ASCII//TRANSLIT//IGNORE",
            $texto
        );

        if ($convertido !== false) {
            $texto = $convertido;
        }
    }

    $texto = preg_replace('/[^A-Za-z0-9_-]+/', '_', $texto);
    $texto = trim($texto, "_-");

    return $texto !== "" ? $texto : "monitor";
}

/* Normaliza o período salvo. */
function certificadoZipNormalizarPeriodo($periodo)
{
    $periodo = trim((string) $periodo);
    $periodo = preg_replace('/\s+/', ' ', $periodo);

    $opcoes = [
        "1º" => "1º",
        "1º semestre" => "1º",
        "2º" => "2º",
        "2º semestre" => "2º",
        "1º e 2º" => "1º e 2º",
        "1º e 2º semestres" => "1º e 2º"
    ];

    return $opcoes[$periodo] ?? $periodo;
}

/* Escreve um campo centralizado sobre o modelo original. */
function certificadoZipEscreverCampo(
    $pdf,
    $x,
    $y,
    $largura,
    $texto,
    $tamanho = 12
) {
    $texto = trim((string) $texto);

    if ($texto === "") {
        return;
    }

    $textoPDF = certificadoZipTextoPDF($texto);
    $fonte = $tamanho;

    $pdf->SetTextColor(5, 31, 72);

    while ($fonte > 7) {
        $pdf->SetFont("Times", "", $fonte);

        if ($pdf->GetStringWidth($textoPDF) <= $largura) {
            break;
        }

        $fonte--;
    }

    $pdf->SetFont("Times", "", $fonte);
    $pdf->SetXY($x, $y);
    $pdf->Cell($largura, 6, $textoPDF, 0, 0, "C");
}

/* Busca o professor associado à disciplina. */
function certificadoZipBuscarProfessor($mysqli, $idDisciplina)
{
    $nomeProfessor = "";

    $sql = "
        SELECT p.nome
        FROM professor p
        WHERE FIND_IN_SET(
            ?,
            REPLACE(COALESCE(p.id_disciplinas, ''), ' ', '')
        ) > 0
        ORDER BY p.id_professor ASC
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        return "";
    }

    $idTexto = (string) $idDisciplina;
    $stmt->bind_param("s", $idTexto);

    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        $professor = $resultado->fetch_assoc();

        if ($professor) {
            $nomeProfessor = trim((string) $professor["nome"]);
        }
    }

    $stmt->close();

    return $nomeProfessor;
}

/* Pasta temporária exclusiva para este processamento. */
try {
    $identificador = bin2hex(random_bytes(8));
} catch (Throwable $erro) {
    http_response_code(500);
    exit("Não foi possível preparar os arquivos temporários.");
}

$pastaTemporaria = rtrim(
    sys_get_temp_dir(),
    DIRECTORY_SEPARATOR
) . DIRECTORY_SEPARATOR . "sacm_certificados_" . $identificador;

if (!mkdir($pastaTemporaria, 0700, true)) {
    http_response_code(500);
    exit("Não foi possível criar a pasta temporária.");
}

$caminhoZip = $pastaTemporaria
    . DIRECTORY_SEPARATOR
    . "certificados_monitoria.zip";

$zip = new ZipArchive();

if ($zip->open(
    $caminhoZip,
    ZipArchive::CREATE | ZipArchive::OVERWRITE
) !== true) {
    @rmdir($pastaTemporaria);
    http_response_code(500);
    exit("Não foi possível criar o arquivo ZIP.");
}

$quantidadePDFs = 0;
$nomesUsados = [];

try {
    foreach ($certificados as $certificado) {
        $nomeAluno = trim((string) $certificado["nome_monitor"]);
        $disciplina = trim((string) $certificado["disciplina"]);
        $horas = (int) $certificado["carga_horaria"];
        $periodo = certificadoZipNormalizarPeriodo(
            $certificado["periodo"]
        );

        $idDisciplinaCertificado = (int) $certificado["id_disciplinas"];

        $nomeProfessor = certificadoZipBuscarProfessor(
            $mysqli,
            $idDisciplinaCertificado
        );

        /* Cria o PDF horizontal e usa o modelo original como fundo. */
        $pdf = new FPDF("L", "mm", "A4");
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $pdf->Image($modelo, 0, 0, 297, 210);

        /* Nome do estudante. */
        certificadoZipEscreverCampo(
            $pdf,
            125,
            62,
            150,
            $nomeAluno,
            12
        );

        /* Disciplina ou atividade. */
        certificadoZipEscreverCampo(
            $pdf,
            88,
            80,
            175,
            $disciplina,
            12
        );

        /* Professor orientador. */
        if ($nomeProfessor !== "") {
            certificadoZipEscreverCampo(
                $pdf,
                104,
                90,
                160,
                $nomeProfessor,
                12
            );
        }

        /* Carga horária. */
        certificadoZipEscreverCampo(
            $pdf,
            109,
            117,
            16,
            (string) $horas,
            11
        );

        /* Período. */
        certificadoZipEscreverCampo(
            $pdf,
            210,
            117,
            27,
            $periodo,
            10
        );

        /*
         * Preserva as linhas originais para assinatura manual.
         * Insere imagens somente se forem fornecidas pela escola.
         */
        $assinaturaGestao = __DIR__ . "/../img/assinatura-gestao.png";
        $assinaturaProfessor = __DIR__ . "/../img/assinatura-professor.png";

        if (is_file($assinaturaGestao)) {
            $pdf->Image($assinaturaGestao, 43, 153, 55, 10);
        }

        if (is_file($assinaturaProfessor)) {
            $pdf->Image($assinaturaProfessor, 190, 153, 55, 10);
        }

        /* Nome do PDF dentro do ZIP. */
        $nomeBase = certificadoZipNomeArquivo($nomeAluno);
        $nomeDisciplina = certificadoZipNomeArquivo($disciplina);

        $nomePDF = $nomeBase . "_" . $nomeDisciplina . "_certificado.pdf";

        /*
         * Evita sobrescrever PDFs caso existam nomes iguais.
         */
        if (isset($nomesUsados[$nomePDF])) {
            $nomesUsados[$nomePDF]++;
            $nomePDF = $nomeBase . "_" . $nomeDisciplina
                . "_" . $nomesUsados[$nomePDF] . "_certificado.pdf";
        } else {
            $nomesUsados[$nomePDF] = 1;
        }

        $caminhoPDF = $pastaTemporaria
            . DIRECTORY_SEPARATOR
            . $nomePDF;

        $pdf->Output("F", $caminhoPDF);

        if (!is_file($caminhoPDF) || filesize($caminhoPDF) === 0) {
            throw new RuntimeException(
                "Não foi possível gerar o certificado de " . $nomeAluno . "."
            );
        }

        if (!$zip->addFile($caminhoPDF, $nomePDF)) {
            throw new RuntimeException(
                "Não foi possível adicionar um certificado ao ZIP."
            );
        }

        $quantidadePDFs++;
    }

    if ($quantidadePDFs < 1) {
        throw new RuntimeException("Nenhum PDF foi gerado.");
    }

    if (!$zip->close()) {
        throw new RuntimeException("Não foi possível finalizar o ZIP.");
    }

} catch (Throwable $erro) {
    @$zip->close();

    foreach (glob($pastaTemporaria . DIRECTORY_SEPARATOR . "*") ?: [] as $arquivo) {
        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    }

    @rmdir($pastaTemporaria);

    error_log("SACM certificados ZIP: " . $erro->getMessage());

    http_response_code(500);
    exit("Ocorreu um erro ao gerar os certificados em ZIP.");
}

if (!is_file($caminhoZip) || filesize($caminhoZip) === 0) {
    foreach (glob($pastaTemporaria . DIRECTORY_SEPARATOR . "*") ?: [] as $arquivo) {
        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    }

    @rmdir($pastaTemporaria);

    http_response_code(500);
    exit("O arquivo ZIP não foi gerado corretamente.");
}

/* Prepara a resposta para o navegador. */
while (ob_get_level() > 0) {
    ob_end_clean();
}

header("Content-Type: application/zip");
header(
    'Content-Disposition: attachment; filename="certificados_monitoria.zip"'
);
header("Content-Length: " . filesize($caminhoZip));
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

readfile($caminhoZip);

/* Remove os arquivos temporários após enviar o ZIP. */
foreach (glob($pastaTemporaria . DIRECTORY_SEPARATOR . "*") ?: [] as $arquivo) {
    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}

@rmdir($pastaTemporaria);
exit;
?>
