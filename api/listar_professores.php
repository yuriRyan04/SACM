
<?php
header('Content-Type: application/json; charset=utf-8');

function responder($dados, $status = 200) {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/conexao.php';

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new Exception('A conexão $mysqli não foi encontrada em conexao.php.');
    }

    $pesquisa = isset($_GET['pesquisa'])
        ? trim((string) $_GET['pesquisa'])
        : '';

    $sql = "
        SELECT
            p.id_professor,
            p.nome,
            p.matricula,
            GROUP_CONCAT(
                DISTINCT d.disciplina
                ORDER BY d.disciplina
                SEPARATOR ', '
            ) AS disciplinas
        FROM professor p
        LEFT JOIN disciplinas d
            ON FIND_IN_SET(
                CAST(d.id_disciplinas AS CHAR),
                REPLACE(COALESCE(p.id_disciplinas, ''), ' ', '')
            ) > 0
        WHERE
            ? = ''
            OR p.nome LIKE CONCAT('%', ?, '%')
            OR CAST(p.matricula AS CHAR) LIKE CONCAT('%', ?, '%')
        GROUP BY
            p.id_professor,
            p.nome,
            p.matricula
        ORDER BY p.nome ASC
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('sss', $pesquisa, $pesquisa, $pesquisa);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $professores = array();

    while ($linha = $resultado->fetch_assoc()) {
        $professores[] = array(
            'id_professor' => (int) $linha['id_professor'],
            'nome' => $linha['nome'],
            'matricula' => $linha['matricula'],
            'disciplinas' => $linha['disciplinas'] ?: ''
        );
    }

    $stmt->close();

    responder(array(
        'sucesso' => true,
        'professores' => $professores
    ));

} catch (Throwable $e) {
    error_log(
        'Erro em listar_professores.php: ' . $e->getMessage()
    );

    responder(array(
        'sucesso' => false,
        'mensagem' => 'Falha ao consultar os professores.',
        'detalhe' => $e->getMessage()
    ), 500);
}
?>