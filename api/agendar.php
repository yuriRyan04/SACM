
<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function responder(int $codigo, array $dados): void
{
    http_response_code($codigo);
    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, [
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ]);
}

$dados = json_decode(file_get_contents('php://input'), true);

if (!is_array($dados)) {
    responder(400, [
        'sucesso' => false,
        'mensagem' => 'Os dados enviados são inválidos.'
    ]);
}

$matricula = trim((string) ($dados['matricula'] ?? ''));
$idMonitor = filter_var(
    $dados['id_monitor'] ?? null,
    FILTER_VALIDATE_INT
);
$data = trim((string) ($dados['data_agendamento'] ?? ''));
$hora = trim((string) ($dados['hora_inicio'] ?? ''));
$assunto = trim((string) ($dados['assunto'] ?? ''));
$observacao = trim((string) ($dados['observacao'] ?? ''));

if (
    $matricula === '' ||
    !$idMonitor ||
    $idMonitor < 1 ||
    $data === '' ||
    $hora === '' ||
    $assunto === ''
) {
    responder(400, [
        'sucesso' => false,
        'mensagem' => 'Preencha todos os campos obrigatórios.'
    ]);
}

/* Validar a data */
$dataObj = DateTime::createFromFormat('!Y-m-d', $data);

if (
    !$dataObj ||
    $dataObj->format('Y-m-d') !== $data ||
    $data < date('Y-m-d')
) {
    responder(400, [
        'sucesso' => false,
        'mensagem' => 'Selecione uma data válida, a partir de hoje.'
    ]);
}

/* Horários permitidos pelo SACM */
$horariosPermitidos = [
    '07:00',
    '07:50',
    '08:40',
    '09:30',
    '10:20',
    '11:10',
    '13:00',
    '13:50',
    '14:40',
    '15:30',
    '16:20'
];

if (!in_array($hora, $horariosPermitidos, true)) {
    responder(400, [
        'sucesso' => false,
        'mensagem' => 'O horário selecionado é inválido.'
    ]);
}

if (mb_strlen($assunto, 'UTF-8') > 150) {
    responder(400, [
        'sucesso' => false,
        'mensagem' => 'O assunto deve ter no máximo 150 caracteres.'
    ]);
}

if (mb_strlen($observacao, 'UTF-8') > 500) {
    responder(400, [
        'sucesso' => false,
        'mensagem' => 'A observação deve ter no máximo 500 caracteres.'
    ]);
}

$mysqli->set_charset('utf8mb4');

try {
    $mysqli->begin_transaction();

    /*
     * Buscar o aluno pela matrícula.
     */
    $sqlAluno = "
        SELECT id_aluno, matricula
        FROM aluno
        WHERE matricula = ?
        LIMIT 1
    ";

    $stmtAluno = $mysqli->prepare($sqlAluno);
    $stmtAluno->bind_param('s', $matricula);
    $stmtAluno->execute();

    $aluno = $stmtAluno->get_result()->fetch_assoc();
    $stmtAluno->close();

    if (!$aluno) {
        $mysqli->rollback();

        responder(404, [
            'sucesso' => false,
            'mensagem' => 'Aluno não encontrado. Entre novamente no SACM.'
        ]);
    }

    $idAluno = (int) $aluno['id_aluno'];

    /*
     * Bloquear a linha do monitor durante a verificação
     * e a criação do agendamento.
     *
     * Isso ajuda a evitar duas reservas simultâneas
     * para o mesmo monitor, desde que a tabela use InnoDB.
     */
    $sqlMonitor = "
        SELECT
            m.id_monitor,
            m.matricula,
            m.id_aluno,
            m.id_disciplinas
        FROM monitores m
        WHERE m.id_monitor = ?
        LIMIT 1
        FOR UPDATE
    ";

    $stmtMonitor = $mysqli->prepare($sqlMonitor);
    $stmtMonitor->bind_param('i', $idMonitor);
    $stmtMonitor->execute();

    $monitor = $stmtMonitor->get_result()->fetch_assoc();
    $stmtMonitor->close();

    if (!$monitor) {
        $mysqli->rollback();

        responder(404, [
            'sucesso' => false,
            'mensagem' => 'Monitor não encontrado.'
        ]);
    }

    /*
     * Impedir que o monitor agende com ele mesmo.
     */
    $matriculaMonitor = trim((string) ($monitor['matricula'] ?? ''));
    $idAlunoMonitor = (int) ($monitor['id_aluno'] ?? 0);

    if (
        ($matriculaMonitor !== '' &&
         $matriculaMonitor === trim((string) $aluno['matricula'])) ||
        ($idAlunoMonitor > 0 && $idAlunoMonitor === $idAluno)
    ) {
        $mysqli->rollback();

        responder(409, [
            'sucesso' => false,
            'mensagem' => 'Você não pode agendar uma monitoria consigo mesmo.'
        ]);
    }

    /*
     * Verificar se o horário já foi reservado.
     *
     * Agendados e realizados ocupam o horário.
     * Cancelados não ocupam o horário.
     * Status NULL também é tratado como ocupado.
     */
    $sqlDisponibilidade = "
        SELECT id_agendamento
        FROM agendamentos
        WHERE id_monitor = ?
          AND data_agendamento = ?
          AND hora_inicio = ?
          AND (
              status IS NULL
              OR status <> 'cancelado'
          )
        LIMIT 1
        FOR UPDATE
    ";

    $stmtDisponibilidade = $mysqli->prepare($sqlDisponibilidade);
    $stmtDisponibilidade->bind_param(
        'iss',
        $idMonitor,
        $data,
        $hora
    );
    $stmtDisponibilidade->execute();

    $ocupado = $stmtDisponibilidade->get_result()->fetch_assoc();
    $stmtDisponibilidade->close();

    if ($ocupado) {
        $mysqli->rollback();

        responder(409, [
            'sucesso' => false,
            'mensagem' => 'Este horário já está ocupado para esse monitor. Escolha outro horário.'
        ]);
    }

    /*
     * Inserir o agendamento.
     */
    $idDisciplina = (int) $monitor['id_disciplinas'];

    $sqlInsert = "
        INSERT INTO agendamentos (
            id_aluno,
            id_monitor,
            id_disciplinas,
            data_agendamento,
            hora_inicio,
            assunto,
            observacao,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'agendado')
    ";

    $stmtInsert = $mysqli->prepare($sqlInsert);
    $stmtInsert->bind_param(
        'iiissss',
        $idAluno,
        $idMonitor,
        $idDisciplina,
        $data,
        $hora,
        $assunto,
        $observacao
    );

    $stmtInsert->execute();
    $stmtInsert->close();

    $mysqli->commit();

    responder(200, [
        'sucesso' => true,
        'mensagem' => 'Monitoria agendada com sucesso!',
        'id_agendamento' => $mysqli->insert_id
    ]);

} catch (Throwable $e) {
    try {
        $mysqli->rollback();
    } catch (Throwable $erroRollback) {
        // A transação pode já ter sido encerrada.
    }

    error_log('Erro em api/agendar.php: ' . $e->getMessage());

    responder(500, [
        'sucesso' => false,
        'mensagem' => 'Não foi possível concluir o agendamento. Verifique os dados e tente novamente.'
    ]);
}
