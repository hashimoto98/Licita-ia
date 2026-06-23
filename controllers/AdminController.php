<?php
class AdminController
{
    private LogAuditoria $log;

    public function __construct()
    {
        $this->log = new LogAuditoria();
    }

    public function index(): void
    {
        AuthController::requerAdmin();

        $db = Database::getInstance()->getConnection();

        $totalUsuarios = (int) $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        $totalEditais  = (int) $db->query("SELECT COUNT(*) FROM editais")->fetchColumn();
        $totalModulos  = (int) $db->query("SELECT COUNT(*) FROM modulos_empresa")->fetchColumn();
        $totalLogs     = (int) $db->query("SELECT COUNT(*) FROM logs_auditoria")->fetchColumn();

        $usuarios   = (new Usuario())->all();
        $filtroAcao = trim($_GET['filtro_acao'] ?? '');
        $logs       = $this->log->listar(200, $filtroAcao ?: null);

        $topAcoes = $db->query(
            "SELECT acao, COUNT(*) as total FROM logs_auditoria GROUP BY acao ORDER BY total DESC LIMIT 10"
        )->fetchAll();

        $acoes = $db->query(
            "SELECT DISTINCT acao FROM logs_auditoria ORDER BY acao"
        )->fetchAll(PDO::FETCH_COLUMN);

        $dbInfo    = $this->dbInfo();
        $pageTitle = 'Administração — ' . APP_NAME;

        include __DIR__ . '/../views/admin/index.php';
    }

    public function criarUsuario(): void
    {
        AuthController::requerAdmin();
        $this->verificarCsrf();

        $nome   = trim($_POST['nome']   ?? '');
        $email  = strtolower(trim($_POST['email']  ?? ''));
        $senha  = trim($_POST['senha']  ?? '');
        $perfil = $_POST['perfil'] ?? 'analista';

        if ($nome === '' || $email === '' || $senha === '') {
            header('Location: ?page=admin&tab=usuarios&erro=campos_obrigatorios');
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: ?page=admin&tab=usuarios&erro=email_invalido');
            exit;
        }
        if (!in_array($perfil, ['admin', 'analista', 'visualizador'], true)) {
            $perfil = 'analista';
        }

        try {
            $novoId = (new Usuario())->criar($nome, $email, $senha, $perfil);
            $this->log->registrar(
                'USUARIO_CRIADO',
                "ID: {$novoId} | Email: {$email} | Perfil: {$perfil}",
                $_SESSION['usuario_id'] ?? null
            );
            header('Location: ?page=admin&tab=usuarios&ok=usuario_criado');
        } catch (\Exception) {
            header('Location: ?page=admin&tab=usuarios&erro=email_duplicado');
        }
        exit;
    }

    public function toggleUsuario(): void
    {
        AuthController::requerAdmin();
        $this->verificarCsrf();

        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Não é possível alterar sua própria conta.']);
            exit;
        }

        $model   = new Usuario();
        $usuario = $model->findById($id);
        if (!$usuario) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário não encontrado.']);
            exit;
        }

        $novoStatus = !(bool) $usuario['ativo'];
        $model->alterarStatus($id, $novoStatus);
        $this->log->registrar(
            'USUARIO_STATUS',
            "ID: {$id} | Email: {$usuario['email']} | " . ($novoStatus ? 'ativado' : 'desativado'),
            $_SESSION['usuario_id'] ?? null
        );

        echo json_encode(['sucesso' => true, 'novo_status' => $novoStatus]);
        exit;
    }

    public function limparLogs(): void
    {
        AuthController::requerAdmin();
        $this->verificarCsrf();

        $dias      = max(1, (int) ($_POST['dias'] ?? LOG_RETENTION_DAYS));
        $removidos = $this->log->limparAntigos($dias);
        $this->log->registrar(
            'LOGS_LIMPOS',
            "Removidos: {$removidos} registros (>{$dias} dias)",
            $_SESSION['usuario_id'] ?? null
        );

        header('Content-Type: application/json');
        echo json_encode(['sucesso' => true, 'removidos' => $removidos]);
        exit;
    }

    public function healthCheck(): void
    {
        AuthController::requerAdmin();
        header('Content-Type: application/json');

        echo json_encode([
            'db'         => $this->checkDb(),
            'openrouter' => $this->checkOpenRouter(),
            'pncp'       => $this->checkPncp(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    private function checkDb(): array
    {
        try {
            Database::getInstance()->getConnection()->query('SELECT 1');
            $info = DB_DRIVER === 'sqlite' && file_exists(DB_SQLITE_PATH)
                ? 'SQLite — ' . round(filesize(DB_SQLITE_PATH) / 1024, 1) . ' KB'
                : 'MySQL — ' . DB_NAME . '@' . DB_HOST;
            return ['ok' => true, 'info' => $info];
        } catch (\Exception $e) {
            return ['ok' => false, 'info' => $e->getMessage()];
        }
    }

    private function checkOpenRouter(): array
    {
        $key = OPENROUTER_API_KEY;
        if (!$key || str_starts_with($key, 'sua-')) {
            return ['ok' => false, 'info' => 'Chave não configurada'];
        }

        $ch = curl_init('https://openrouter.ai/api/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            return ['ok' => false, 'info' => "Autenticação falhou — HTTP {$code}"];
        }

        // Verifica se o modelo configurado existe na lista de modelos disponíveis
        $data     = json_decode($body, true);
        $modelIds = array_column($data['data'] ?? [], 'id');
        $modelOk  = in_array(OPENROUTER_MODEL, $modelIds, true);

        return [
            'ok'   => $modelOk,
            'info' => $modelOk
                ? 'Conectado — modelo: ' . OPENROUTER_MODEL
                : 'Autenticado, mas modelo "' . OPENROUTER_MODEL . '" não encontrado — verifique OPENROUTER_MODEL no .env',
        ];
    }

    private function checkPncp(): array
    {
        $url = PNCP_SEARCH_URL . '?tipos_documento=edital&q=sistema&pagina=1&tam_pagina=1';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'LicitAI/' . APP_VERSION,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $code === 200, 'info' => $code === 200 ? 'Conectado (API search v2)' : "HTTP {$code}"];
    }

    private function dbInfo(): array
    {
        if (DB_DRIVER === 'sqlite' && file_exists(DB_SQLITE_PATH)) {
            return [
                'driver'  => 'SQLite',
                'tamanho' => round(filesize(DB_SQLITE_PATH) / 1024, 1) . ' KB',
                'arquivo' => basename(DB_SQLITE_PATH),
            ];
        }
        return [
            'driver'  => 'MySQL',
            'tamanho' => '—',
            'arquivo' => DB_NAME . '@' . DB_HOST,
        ];
    }

    private function verificarCsrf(): void
    {
        $token    = $_POST['csrf_token'] ?? '';
        $esperado = $_SESSION['csrf_token'] ?? '';
        if (!$esperado || !hash_equals($esperado, $token)) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }
    }
}
