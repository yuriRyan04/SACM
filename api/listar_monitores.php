
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissao_monitor.php';

sacmExigirUsuario();

$idDisciplina = filter_var(
    $_GET['disciplina_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$idDisciplina || $idDisciplina <= 0) {
    sacmResponderPermissao(
        false,
        'Informe uma disciplina válida.',
        [],
        400
    );
}

sacmExigirDisciplina($mysqli, (int)$idDisciplina);

try {
    $sql = "
        SELECT
            m.id_monitor,
            m.matricula,
            m.id_aluno,
            d.id_disciplinas,
            d.disciplina,
            d.area,
            a.Nome AS nome_aluno,
            (
                SELECT COUNT(DISTINCT CAST(m2.id_disciplinas AS UNSIGNED))
                FROM monitores m2
                WHERE m2.matricula = m.matricula
            ) AS quantidade_monitorias
        FROM monitores m
        INNER JOIN disciplinas d
            ON d.id_disciplinas = CAST(m.id_disciplinas AS UNSIGNED)
        LEFT JOIN aluno a
            ON a.id_aluno = m.id_aluno
        WHERE d.id_disciplinas = ?
        ORDER BY a.Nome, m.matricula
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $idDisciplina);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $monitores = [];

    while ($linha = $resultado->fetch_assoc()) {
        $monitores[] = $linha;
    }

    $stmt->close();

    echo json_encode($monitores, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    sacmResponderPermissao(
        false,
        'Não foi possível carregar os monitores.',
        [],
        500
    );
}
?>