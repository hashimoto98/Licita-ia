<?php
/**
 * LicitAI - Controller de Módulos da Empresa (CRUD)
 */
class ModulosController
{
    private Modulo $model;
    private LogAuditoria $log;

    public function __construct()
    {
        $this->model = new Modulo();
        $this->log   = new LogAuditoria();
    }

    public function listar(): void
    {
        AuthController::requerAutenticacao();
        $modulos    = $this->model->todos(apenasAtivos: false);
        $categorias = $this->model->categorias();
        include __DIR__ . '/../views/modulos/index.php';
    }

    public function formulario(?int $id = null): void
    {
        AuthController::requerAutenticacao();
        $modulo     = $id ? $this->model->findById($id) : null;
        $categorias = $this->model->categorias();
        include __DIR__ . '/../views/modulos/form.php';
    }

    public function salvar(): void
    {
        AuthController::requerAutenticacao();
        $this->verificarCsrf();

        $id   = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $nome = trim($_POST['nome'] ?? '');

        if (empty($nome)) {
            $this->redirecionar('?page=modulos&erro=nome_obrigatorio');
        }

        $palavrasRaw = trim($_POST['palavras_chave'] ?? '');
        $dados = [
            'nome'          => $nome,
            'descricao'     => trim($_POST['descricao']  ?? ''),
            'palavras_chave'=> $palavrasRaw,
            'categoria'     => trim($_POST['categoria']  ?? ''),
            'versao'        => trim($_POST['versao']     ?? ''),
            'ativo'         => isset($_POST['ativo']) ? 1 : 0,
        ];

        $usuarioId = $_SESSION['usuario_id'] ?? null;

        if ($id) {
            $this->model->atualizar($id, $dados);
            $this->log->registrar('MODULO_ATUALIZADO', "ID: {$id} | Nome: {$nome}", $usuarioId);
            $this->redirecionar('?page=modulos&ok=atualizado');
        } else {
            $novoId = $this->model->criar($dados);
            $this->log->registrar('MODULO_CRIADO', "ID: {$novoId} | Nome: {$nome}", $usuarioId);
            $this->redirecionar('?page=modulos&ok=criado');
        }
    }

    public function excluir(int $id): void
    {
        AuthController::requerAdmin(); // exclusão é ação destrutiva — exige perfil admin
        $this->verificarCsrf();

        $modulo = $this->model->findById($id);
        if ($modulo) {
            $this->model->excluir($id);
            $this->log->registrar('MODULO_EXCLUIDO', "ID: {$id} | Nome: {$modulo['nome']}", $_SESSION['usuario_id'] ?? null);
        }

        $this->redirecionar('?page=modulos&ok=excluido');
    }

    public function toggleStatus(int $id): void
    {
        AuthController::requerAutenticacao();
        $modulo = $this->model->findById($id);
        if ($modulo) {
            $novoStatus = $modulo['ativo'] ? 0 : 1;
            $this->model->alterarStatus($id, (bool) $novoStatus);
        }
        header('Content-Type: application/json');
        echo json_encode(['sucesso' => true]);
        exit;
    }

    private function verificarCsrf(): void
    {
        $token    = $_POST['csrf_token'] ?? '';
        $esperado = $_SESSION['csrf_token'] ?? '';
        if (!$esperado || !hash_equals($esperado, $token)) {
            http_response_code(403);
            die('CSRF inválido.');
        }
    }

    private function redirecionar(string $url): never
    {
        header("Location: {$url}");
        exit;
    }
}
