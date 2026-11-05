
<?php
/*
|--------------------------------------------------------------------------
| SACM — PERMISSÕES DO CADASTRO DE MONITORES
|--------------------------------------------------------------------------
| Gestão: todas as disciplinas.
| Professor: somente disciplinas em professor.id_disciplinas.
| A sessão deve conter matricula e tipo.
*/

function sacmTipoUsuario(): string
{
    return strtolower(trim((string)($_SESSION['tipo'] ?? '')));
}

function sacmEhGestao(): bool
{
    return in_array(sacmTipoUsuario(), [
        'gestao',
        'gestão',
        'admin',
        'administrador'
    ], true);
}

function sacmEhProfessor(): bool
{
    return sacmTipoUsuario() === 'professor';
}

function sacmResponderPermissao(
    bool $sucesso,
    string $mensagem,
    array $dados = [],
    int $status = 200
): void {
    http_response_code($status);

    echo json_encode(
        array_merge([
            'sucesso' => $sucesso,
            'mensagem' => $mensagem
        ], $dados),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

function sacmExigirUsuario(): void
{
    if (empty($_SESSION['matricula']) || (
        !sacmEhGestao() && !sacmEhProfessor()
    )) {
        sacmResponderPermissao(
            false,
            'Acesso não autorizado. Entre novamente no SACM.',
            [],
            401
        );
    }
}

/*
 * Retorna os IDs das disciplinas associadas ao professor.
 * Exemplo: "6,5" ou "[6,5]" retorna [6, 5].
 */
function sacmObterDisciplinasProfessor(mysqli $mysqli): array
{
    if (!sacmEhProfessor()) {
        return [];
    }

    $matricula = trim((string)$_SESSION['matricula']);

    $stmt = $mysqli->prepare(
        "SELECT id_disciplinas
         FROM professor
         WHERE matricula = ?
         LIMIT 1"
    );

    $stmt->bind_param('s', $matricula);
    $stmt->execute();

    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$resultado) {
        return [];
    }

    preg_match_all(
        '/\d+/',
        (string)($resultado['id_disciplinas'] ?? ''),
        $matches
    );

    $ids = array_map('intval', $matches[0] ?? []);
    $ids = array_filter($ids, fn($id) => $id > 0);

    return array_values(array_unique($ids));
}

/*
 * Verifica se o usuário pode acessar uma disciplina.
 */
function sacmPodeAcessarDisciplina(
    mysqli $mysqli,
    int $idDisciplina
): bool {
    if ($idDisciplina <= 0) {
        return false;
    }

    if (sacmEhGestao()) {
        return true;
    }

    if (!sacmEhProfessor()) {
        return false;
    }

    return in_array(
        $idDisciplina,
        sacmObterDisciplinasProfessor($mysqli),
        true
    );
}

/*
 * Valida uma disciplina recebida pela requisição.
 */
function sacmExigirDisciplina(
    mysqli $mysqli,
    int $idDisciplina
): void {
    sacmExigirUsuario();

    if (!sacmPodeAcessarDisciplina($mysqli, $idDisciplina)) {
        sacmResponderPermissao(
            false,
            'Você não tem permissão para acessar esta disciplina.',
            [],
            403
        );
    }
}
?>