
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissao_monitor.php';

sacmExigirUsuario();

try {
    if (sacmEhGestao()) {
        $sql = "SELECT id_disciplinas, disciplina, area,
                       series, cursos, limite_monitores
                FROM disciplinas
                ORDER BY area, disciplina";

        $resultado = $mysqli->query($sql);
        $disciplinas = [];

        while ($linha = $resultado->fetch_assoc()) {
            $disciplinas[] = $linha;
        }

        echo json_encode(
            $disciplinas,
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    $ids = sacmObterDisciplinasProfessor($mysqli);

    if (!$ids) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $tipos = str_repeat('i', count($ids));

    $sql = "SELECT id_disciplinas, disciplina, area,
                   series, cursos, limite_monitores
            FROM disciplinas
            WHERE id_disciplinas IN ($placeholders)
            ORDER BY area, disciplina";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($tipos, ...$ids);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $disciplinas = [];

    while ($linha = $resultado->fetch_assoc()) {
        $disciplinas[] = $linha;
    }

    $stmt->close();

    echo json_encode(
        $disciplinas,
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    sacmResponderPermissao(
        false,
        'Não foi possível carregar as disciplinas.',
        [],
        500
    );
}
?>