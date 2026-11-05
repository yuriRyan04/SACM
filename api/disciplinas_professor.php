<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(bool $sucesso, string $mensagem, array $dados = [], int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        array_merge([
            'sucesso' => $sucesso,
            'mensagem' => $mensagem
        ], $dados),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

try {
    require_once __DIR__ . '/conexao.php';

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new RuntimeException(
            'O arquivo conexao.php não disponibilizou a variável $mysqli.'
        );
    }

    $mysqli->set_charset('utf8mb4');
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ==========================================
    // TRATAMENTO DA REQUISIÇÃO DELETE (EXCLUIR)
    // ==========================================
    if ($metodo === 'DELETE') {
        $corpo = file_get_contents('php://input');
        $dados = json_decode($corpo, true);

        // Permite pegar o ID pelo JSON do body ou via query string (?id_professor=X)
        $idProfessor = (int)($dados['id_professor'] ?? $_GET['id_professor'] ?? 0);

        if ($idProfessor <= 0) {
            responder(false, 'Identificador do professor inválido para exclusão.', [], 400);
        }

        $stmt = $mysqli->prepare('DELETE FROM professor WHERE id_professor = ?');
        $stmt->bind_param('i', $idProfessor);
        $stmt->execute();
        
        $afetados = $stmt->affected_rows;
        $stmt->close();

        if ($afetados === 0) {
            responder(false, 'Professor não encontrado ou já excluído.', [], 404);
        }

        responder(true, 'Professor excluído com sucesso.');
    }

    // ==========================================
    // TRATAMENTO DA REQUISIÇÃO POST (SALVAR)
    // ==========================================
    if ($metodo === 'POST') {
        $corpo = file_get_contents('php://input');
        $dados = json_decode($corpo, true);

        if (!is_array($dados)) {
            responder(false, 'Corpo da requisição JSON inválido.', [], 400);
        }

        $idProfessor = (int)($dados['id_professor'] ?? 0);
        $disciplinasIds = $dados['disciplinas'] ?? [];

        if ($idProfessor <= 0) {
            responder(false, 'Identificador do professor inválido.', [], 400);
        }

        if (!is_array($disciplinasIds)) {
            $disciplinasIds = [];
        }

        $idsFiltrados = array_values(array_filter(
            array_map('trim', $disciplinasIds),
            static fn($id) => ctype_digit((string)$id) && (int)$id > 0
        ));

        $stringDisciplinas = implode(',', $idsFiltrados);

        $stmt = $mysqli->prepare(
            'UPDATE professor SET id_disciplinas = ? WHERE id_professor = ?'
        );
        $stmt->bind_param('si', $stringDisciplinas, $idProfessor);
        $stmt->execute();
        $stmt->close();

        responder(true, 'Disciplinas atualizadas com sucesso.', [
            'id_disciplinas' => array_map('strval', $idsFiltrados)
        ]);
    }

    // ==========================================
    // TRATAMENTO DA REQUISIÇÃO GET (CARREGAR)
    // ==========================================
    $resultadoProfessores = $mysqli->query(
        'SELECT id_professor, nome, matricula, id_disciplinas
         FROM professor
         ORDER BY nome ASC'
    );

    $professores = [];

    while ($linha = $resultadoProfessores->fetch_assoc()) {
        $ids = array_filter(
            array_map(
                'trim',
                explode(',', (string)($linha['id_disciplinas'] ?? ''))
            ),
            static fn($id) => ctype_digit($id) && (int)$id > 0
        );

        $professores[] = [
            'id_professor' => (int)$linha['id_professor'],
            'nome' => (string)($linha['nome'] ?? ''),
            'matricula' => (string)($linha['matricula'] ?? ''),
            'id_disciplinas' => array_values(
                array_unique(array_map('strval', $ids))
            )
        ];
    }

    $resultadoProfessores->free();

    $resultadoDisciplinas = $mysqli->query(
        'SELECT id_disciplinas, disciplina, area, series, cursos
         FROM disciplinas
         ORDER BY disciplina ASC'
    );

    $disciplinas = [];

    while ($linha = $resultadoDisciplinas->fetch_assoc()) {
        $disciplinas[] = [
            'id_disciplinas' => (int)$linha['id_disciplinas'],
            'disciplina' => (string)$linha['disciplina'],
            'area' => (string)($linha['area'] ?? ''),
            'series' => (string)($linha['series'] ?? 'Todas'),
            'cursos' => (string)($linha['cursos'] ?? 'Todos')
        ];
    }

    $resultadoDisciplinas->free();

    responder(true, 'Dados carregados.', [
        'professores' => $professores,
        'disciplinas' => $disciplinas
    ]);

} catch (Throwable $erro) {
    error_log(
        '[SACM disciplinas_professor.php] ' .
        get_class($erro) . ': ' . $erro->getMessage()
    );

    responder(false, 'Falha no processamento da requisição.', [
        'detalhe' => $erro->getMessage()
    ], 500);
}
?>