
<?php
/*
|--------------------------------------------------------------------------
| SACM — CERTIFICADO DE MONITORIA
| Arquivo: api/gerar_certificado.php
| Formato: A4 horizontal
| Logo SACM no cabeçalho esquerdo
| Logo RN no cabeçalho direito
| Logo CEEP como marca-d'água
| Assinaturas reposicionadas
| Campo Semestre
| Data de emissão na parte inferior
|--------------------------------------------------------------------------
*/

session_start();

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/permissao_monitor.php";

sacmExigirUsuario();

if (!sacmEhGestao()) {
    http_response_code(403);
    exit("Apenas a Gestão pode emitir certificados.");
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    exit("Erro na conexão com o banco de dados.");
}

// ========================================
// BIBLIOTECA FPDF
// ========================================

$fpdfPath = __DIR__ . "/lib/fpdf19/fpdf.php";

if (!file_exists($fpdfPath)) {
    http_response_code(500);
    exit("A biblioteca FPDF não foi encontrada.");
}

require_once $fpdfPath;

// ========================================
// CAMINHOS DAS LOGOS
// ========================================

$logoSACM = __DIR__ . "/../img/SACM.png";

$logoCEEP = __DIR__ . "/../img/CEEPPLG.jpeg";

$possiveisLogosRN = [
    __DIR__ . "/../img/RN.png",
    __DIR__ . "/../img/RN.jpeg",
    __DIR__ . "/../img/GovernoRN.png",
    __DIR__ . "/../img/logo-rn.png",
    __DIR__ . "/../img/rio-grande-do-norte.png"
];

$logoRN = "";

foreach ($possiveisLogosRN as $arquivo) {
    if (is_file($arquivo)) {
        $logoRN = $arquivo;
        break;
    }
}

// ========================================
// CRIAR MARCA-D'ÁGUA DO CEEP
// ========================================

function criarMarcaDaguaCEEP($arquivoOriginal)
{
    if (!is_file($arquivoOriginal)) {
        return "";
    }

    if (
        !function_exists("imagecreatefromstring") ||
        !function_exists("imagejpeg")
    ) {
        return "";
    }

    $conteudo = @file_get_contents($arquivoOriginal);

    if ($conteudo === false) {
        return "";
    }

    $original = @imagecreatefromstring($conteudo);

    if (!$original) {
        return "";
    }

    $largura = imagesx($original);
    $altura = imagesy($original);

    if ($largura <= 0 || $altura <= 0) {
        imagedestroy($original);
        return "";
    }

    // Limitar dimensões para manter boa resolução.
    $fator = min(
        1,
        1400 / $largura,
        1400 / $altura
    );

    $novaLargura = max(1, (int) ($largura * $fator));
    $novaAltura = max(1, (int) ($altura * $fator));

    $redimensionada = imagecreatetruecolor(
        $novaLargura,
        $novaAltura
    );

    if (!$redimensionada) {
        imagedestroy($original);
        return "";
    }

    $branco = imagecolorallocate(
        $redimensionada,
        255,
        255,
        255
    );

    imagefill($redimensionada, 0, 0, $branco);

    imagecopyresampled(
        $redimensionada,
        $original,
        0,
        0,
        0,
        0,
        $novaLargura,
        $novaAltura,
        $largura,
        $altura
    );

    // Criar imagem branca para suavizar a marca-d'água.
    $fundo = imagecreatetruecolor(
        $novaLargura,
        $novaAltura
    );

    if (!$fundo) {
        imagedestroy($original);
        imagedestroy($redimensionada);
        return "";
    }

    $brancoFundo = imagecolorallocate(
        $fundo,
        255,
        255,
        255
    );

    imagefill($fundo, 0, 0, $brancoFundo);

    // 18% da imagem original e 82% de branco.
    imagecopymerge(
        $fundo,
        $redimensionada,
        0,
        0,
        0,
        0,
        $novaLargura,
        $novaAltura,
        18
    );

    $temporario = rtrim(
        sys_get_temp_dir(),
        DIRECTORY_SEPARATOR
    ) . DIRECTORY_SEPARATOR
      . "sacm_ceep_" . uniqid() . ".jpg";

    $salvou = imagejpeg(
        $fundo,
        $temporario,
        95
    );

    imagedestroy($original);
    imagedestroy($redimensionada);
    imagedestroy($fundo);

    return $salvou ? $temporario : "";
}

$marcaDaguaCEEP = criarMarcaDaguaCEEP($logoCEEP);

// ========================================
// IDENTIFICAÇÃO DO CERTIFICADO
// ========================================

$idMonitor = filter_input(
    INPUT_GET,
    "id_monitor",
    FILTER_VALIDATE_INT
);

$idDisciplina = filter_input(
    INPUT_GET,
    "id_disciplinas",
    FILTER_VALIDATE_INT
);

if (!$idMonitor || !$idDisciplina) {
    http_response_code(400);
    exit("Monitor ou disciplina inválidos.");
}

// ========================================
// CONSULTAR CERTIFICADO
// ========================================

$sql = "
    SELECT
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
    WHERE cm.id_monitor = ?
      AND cm.id_disciplinas = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit("Erro ao consultar o certificado.");
}

$stmt->bind_param(
    "ii",
    $idMonitor,
    $idDisciplina
);

$stmt->execute();

$resultado = $stmt->get_result();
$certificado = $resultado->fetch_assoc();

$stmt->close();

if (!$certificado) {
    if ($marcaDaguaCEEP && is_file($marcaDaguaCEEP)) {
        @unlink($marcaDaguaCEEP);
    }

    http_response_code(404);
    exit("Certificado não encontrado.");
}

// ========================================
// CONSULTAR PROFESSOR ORIENTADOR
// ========================================

$nomeProfessor = "";

$sqlProfessor = "
    SELECT p.nome
    FROM professor p
    WHERE FIND_IN_SET(
        ?,
        REPLACE(p.id_disciplinas, ' ', '')
    ) > 0
    ORDER BY p.id_professor ASC
    LIMIT 1
";

$stmtProfessor = $mysqli->prepare($sqlProfessor);

if ($stmtProfessor) {
    $idDisciplinaTexto = (string) $idDisciplina;

    $stmtProfessor->bind_param(
        "s",
        $idDisciplinaTexto
    );

    $stmtProfessor->execute();

    $resultadoProfessor = $stmtProfessor->get_result();
    $professor = $resultadoProfessor->fetch_assoc();

    if ($professor) {
        $nomeProfessor = trim(
            (string) $professor["nome"]
        );
    }

    $stmtProfessor->close();
}

// ========================================
// DADOS DO CERTIFICADO
// ========================================

$nomeAluno = trim(
    (string) $certificado["nome_monitor"]
);

$disciplina = trim(
    (string) $certificado["disciplina"]
);

$cargaHoraria = (int) $certificado["carga_horaria"];

// O banco continua utilizando a coluna "periodo".
$semestre = trim(
    (string) $certificado["periodo"]
);

// DATA DE EMISSÃO
if (!empty($certificado["data_emissao"])) {
    $timestamp = strtotime(
        $certificado["data_emissao"]
    );

    $dataEmissao = $timestamp !== false
        ? date("d/m/Y", $timestamp)
        : date("d/m/Y");
} else {
    $dataEmissao = date("d/m/Y");
}

// ========================================
// CLASSE DO CERTIFICADO
// ========================================

class CertificadoPDF extends FPDF
{
    public $logoSACM = "";
    public $logoRN = "";
    public $marcaDaguaCEEP = "";

    // CONVERTER UTF-8 PARA WINDOWS-1252
    public function texto($texto)
    {
        $texto = (string) $texto;

        $convertido = @iconv(
            "UTF-8",
            "Windows-1252//TRANSLIT",
            $texto
        );

        return $convertido !== false
            ? $convertido
            : utf8_decode($texto);
    }

    // DESENHAR POLÍGONOS
    public function poligono($pontos, $cor)
    {
        $this->SetFillColor(
            $cor[0],
            $cor[1],
            $cor[2]
        );

        $comando = "";

        foreach ($pontos as $i => $ponto) {
            $x = $ponto[0] * $this->k;
            $y = ($this->h - $ponto[1]) * $this->k;

            $comando .= sprintf(
                "%.2F %.2F %s ",
                $x,
                $y,
                $i === 0 ? "m" : "l"
            );
        }

        $this->_out($comando . "h f");
    }

    // TEXTO CENTRALIZADO COM AJUSTE DE FONTE
    public function centralizar(
        $texto,
        $y,
        $fonte = 13,
        $negrito = false
    ) {
        $texto = $this->texto($texto);
        $tamanho = $fonte;
        $largura = 257;

        do {
            $this->SetFont(
                "Times",
                $negrito ? "B" : "",
                $tamanho
            );

            if (
                $this->GetStringWidth($texto)
                <= $largura
            ) {
                break;
            }

            $tamanho -= 0.5;

        } while ($tamanho > 8);

        $this->SetXY(20, $y);

        $this->Cell(
            $largura,
            8,
            $texto,
            0,
            0,
            "C"
        );
    }

    // ====================================
    // CABEÇALHO E MARCA-D'ÁGUA
    // ====================================

    public function Header()
    {
        $azul = [0, 45, 125];
        $azulEscuro = [0, 24, 92];
        $vermelho = [245, 15, 35];

        // FUNDO BRANCO
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, 297, 210, "F");

        // MARCA-D'ÁGUA CEEP
        if (
            $this->marcaDaguaCEEP !== "" &&
            is_file($this->marcaDaguaCEEP)
        ) {
            $this->Image(
                $this->marcaDaguaCEEP,
                76,
                59,
                145,
                112
            );
        }

        // MOLDURA AZUL
        $this->SetDrawColor(...$azul);
        $this->SetLineWidth(1.7);

        $this->Line(38, 8, 290, 8);
        $this->Line(7, 51, 7, 203);
        $this->Line(7, 203, 250, 203);
        $this->Line(290, 8, 290, 160);

        // CANTO SUPERIOR ESQUERDO
        $this->poligono([
            [0, 0],
            [30, 0],
            [0, 30]
        ], $azul);

        $this->poligono([
            [32, 0],
            [43, 0],
            [0, 43],
            [0, 32]
        ], $vermelho);

        $this->poligono([
            [0, 48],
            [30, 16],
            [0, 54]
        ], $azulEscuro);

        // CANTO INFERIOR DIREITO
        $this->poligono([
            [297, 210],
            [267, 210],
            [297, 180]
        ], $azul);

        $this->poligono([
            [265, 210],
            [254, 210],
            [297, 167],
            [297, 178]
        ], $vermelho);

        $this->poligono([
            [297, 162],
            [267, 194],
            [297, 156]
        ], $azulEscuro);

        // DETALHES VERMELHOS
        $this->SetDrawColor(...$vermelho);
        $this->SetLineWidth(1.4);

        $this->Line(264, 11, 286, 11);
        $this->Line(286, 11, 286, 29);

        $this->Line(11, 181, 11, 199);
        $this->Line(11, 199, 31, 199);

        // LOGO SACM
        if (
            $this->logoSACM !== "" &&
            is_file($this->logoSACM)
        ) {
            $this->Image(
                $this->logoSACM,
                32,
                12,
                27
            );
        }

        // LOGO RIO GRANDE DO NORTE
        if (
            $this->logoRN !== "" &&
            is_file($this->logoRN)
        ) {
            $this->Image(
                $this->logoRN,
                239,
                12,
                25,
                25
            );
        }

        // NOME DA INSTITUIÇÃO
        $this->SetTextColor(...$azulEscuro);
        $this->SetFont("Times", "B", 16);

        $this->SetXY(59, 12);

        $this->Cell(
            179,
            8,
            $this->texto(
                "CENTRO ESTADUAL DE EDUCAÇÃO PROFISSIONAL"
            ),
            0,
            0,
            "C"
        );

        $this->SetXY(59, 21);

        $this->Cell(
            179,
            8,
            $this->texto(
                "PROFESSORA LOURDINHA GUERRA"
            ),
            0,
            0,
            "C"
        );

        // TÍTULO
        $this->centralizar(
            "CERTIFICADO DE MONITORIA",
            36,
            27,
            true
        );

        // LINHAS DECORATIVAS
        $this->SetDrawColor(0, 125, 230);
        $this->SetLineWidth(0.45);

        $this->Line(76, 55, 130, 55);
        $this->Line(164, 55, 221, 55);

        // FAIXAS CENTRAIS
        $this->poligono([
            [137, 51],
            [144, 51],
            [139, 58],
            [132, 58]
        ], $azul);

        $this->poligono([
            [146, 51],
            [153, 51],
            [148, 58],
            [141, 58]
        ], $vermelho);

        $this->poligono([
            [155, 51],
            [162, 51],
            [157, 58],
            [150, 58]
        ], $azul);
    }
}

// ========================================
// GERAR PDF A4 HORIZONTAL
// ========================================

$pdf = new CertificadoPDF("L", "mm", "A4");

$pdf->logoSACM = $logoSACM;
$pdf->logoRN = $logoRN;
$pdf->marcaDaguaCEEP = $marcaDaguaCEEP;

$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$pdf->SetTextColor(0, 25, 105);

// TEXTO INICIAL
$pdf->centralizar(
    "Certificamos que o(a) estudante",
    61,
    14
);

// NOME DO MONITOR
$pdf->centralizar(
    $nomeAluno,
    71,
    17,
    true
);

$pdf->SetDrawColor(0, 130, 240);
$pdf->SetLineWidth(0.4);

$pdf->Line(36, 79, 261, 79);

// TEXTO DE MATRÍCULA
$pdf->centralizar(
    "regularmente matriculado(a) nesta instituição, exerceu a função de monitor(a) na disciplina/atividade:",
    81,
    12
);

// DISCIPLINA
$pdf->centralizar(
    $disciplina,
    94,
    16,
    true
);

$pdf->Line(85, 103, 213, 103);

// PROFESSOR ORIENTADOR
$pdf->centralizar(
    "Sob a orientação do(a) Professor(a):",
    105,
    13
);

$pdf->centralizar(
    $nomeProfessor !== ""
        ? $nomeProfessor
        : "Professor(a) responsável",
    114,
    15,
    true
);

$pdf->Line(86, 122, 212, 122);

// DESCRIÇÃO DA MONITORIA
$pdf->centralizar(
    "O(a) estudante atuou junto às turmas dos Cursos Técnicos de Nível Médio em Informática e/ou Redes",
    125,
    12
);

$pdf->centralizar(
    "de Computadores, na forma Articulada Integrada em Tempo Integral.",
    133,
    12
);

// ========================================
// CARGA HORÁRIA E SEMESTRE
// ========================================

$pdf->SetFillColor(233, 245, 255);
$pdf->SetDrawColor(90, 175, 240);
$pdf->SetLineWidth(0.3);

$pdf->Rect(
    23,
    145,
    251,
    13,
    "DF"
);

// DIVISOR CENTRAL
$pdf->Line(148.5, 147, 148.5, 156);

$pdf->SetTextColor(0, 25, 105);
$pdf->SetFont("Times", "B", 15);

// CARGA HORÁRIA
$pdf->SetXY(25, 147);

$pdf->Cell(
    120,
    9,
    $pdf->texto(
        "Carga horária: " .
        $cargaHoraria .
        " horas"
    ),
    0,
    0,
    "C"
);

// SEMESTRE
$pdf->SetXY(151, 147);

$pdf->Cell(
    120,
    9,
    $pdf->texto(
        "Semestre: " . $semestre
    ),
    0,
    0,
    "C"
);

// ========================================
// FINALIDADE DO CERTIFICADO
// ========================================

$pdf->centralizar(
    "Certificado emitido para fins acadêmicos e comprovação de atividades complementares.",
    160,
    10
);

// ========================================
// ASSINATURAS — MAIS PARA BAIXO
// ========================================

$pdf->SetDrawColor(0, 120, 220);
$pdf->SetLineWidth(0.4);

// LINHAS DE ASSINATURA: Y = 177
$pdf->Line(36, 177, 121, 177);
$pdf->Line(175, 177, 266, 177);

$pdf->SetTextColor(0, 25, 105);
$pdf->SetFont("Times", "B", 13);

// DIRETOR
$pdf->SetXY(36, 179);

$pdf->Cell(
    85,
    8,
    $pdf->texto("Diretor"),
    0,
    0,
    "C"
);

// PROFESSOR TUTOR
$pdf->SetXY(175, 179);

$pdf->Cell(
    91,
    8,
    $pdf->texto("Professor(a) Tutor(a)"),
    0,
    0,
    "C"
);

// ========================================
// DATA DE EMISSÃO
// ========================================

$pdf->centralizar(
    "Data de emissão: " . $dataEmissao,
    190,
    11
);

// ========================================
// GERAR PDF NA MEMÓRIA
// ========================================

$nomeArquivo = "certificado_monitoria.pdf";

$pdfGerado = $pdf->Output("S");

// Remover arquivo temporário da marca-d'água.
if (
    $marcaDaguaCEEP !== "" &&
    is_file($marcaDaguaCEEP)
) {
    @unlink($marcaDaguaCEEP);
}

// LIMPAR BUFFER ANTES DE ENVIAR O PDF
while (ob_get_level() > 0) {
    ob_end_clean();
}

header("Content-Type: application/pdf");

header(
    'Content-Disposition: inline; filename="' .
    $nomeArquivo .
    '"'
);

header("Content-Length: " . strlen($pdfGerado));
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

echo $pdfGerado;
exit;
?>
