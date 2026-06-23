<?php
/**
 * LicitAI — Front Controller
 *
 * Ponto de entrada único da aplicação. Inicializa sessão,
 * carrega dependências e despacha para o controller correto.
 */

declare(strict_types=1);

// ─── Config primeiro (define constantes usadas na sessão) ─────────────────────
require_once __DIR__ . '/config/config.php';

// ─── Segurança de sessão ──────────────────────────────────────────────────────
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
// ini_set('session.cookie_secure', '1'); // Habilitar com HTTPS

session_name(SESSION_NAME);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/Usuario.php';
require_once __DIR__ . '/models/Edital.php';
require_once __DIR__ . '/models/Modulo.php';
require_once __DIR__ . '/models/LogAuditoria.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/EditaisController.php';
require_once __DIR__ . '/controllers/ModulosController.php';
require_once __DIR__ . '/controllers/ApiPncpController.php';
require_once __DIR__ . '/controllers/OpenRouterController.php';
require_once __DIR__ . '/controllers/MatchController.php';
require_once __DIR__ . '/controllers/UploadController.php';
require_once __DIR__ . '/controllers/AdminController.php';

// ─── Roteamento ───────────────────────────────────────────────────────────────
$page   = filter_input(INPUT_GET, 'page',   FILTER_SANITIZE_SPECIAL_CHARS) ?? 'dashboard';
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$id     = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$auth    = new AuthController();
$editais = new EditaisController();
$modulos = new ModulosController();

// ─── Ações (POST/GET funcionais) ─────────────────────────────────────────────
if (!empty($action)) {
    match ($action) {
        // Autenticação
        'login'  => $auth->login(),
        'logout' => $auth->logout(),

        // Formulários de cadastro/edição (exibição de página — dependem de $page)
        'novo'   => (function () use ($page, $editais, $modulos) {
            if ($page === 'editais') { $editais->formulario(null); return; }
            if ($page === 'modulos') { $modulos->formulario(null); return; }
            http_response_code(404); echo json_encode(['erro' => 'Ação não encontrada.']); exit;
        })(),
        'editar' => (function () use ($page, $editais, $modulos, $id) {
            if ($page === 'editais') { $editais->formulario($id); return; }
            if ($page === 'modulos') { $modulos->formulario($id); return; }
            http_response_code(404); echo json_encode(['erro' => 'Ação não encontrada.']); exit;
        })(),

        // Módulos CRUD
        'salvar_modulo'  => $modulos->salvar(),
        'excluir_modulo' => $modulos->excluir((int) ($_POST['id'] ?? 0)),
        'toggle_modulo'  => $modulos->toggleStatus($id),

        // Editais CRUD manual
        'salvar_edital'  => $editais->salvarManual(),
        'excluir_edital' => $editais->excluir($id ?: (int) ($_POST['id'] ?? 0)),

        // Análise IA (AJAX JSON)
        'analisar_edital' => $editais->analisar($id ?: (int) ($_POST['id'] ?? 0)),

        // Upload de Termo de Referência (AJAX JSON)
        'upload_tr' => (new UploadController())->uploadTR($id ?: (int) ($_POST['id'] ?? 0)),

        // Captura PNCP (AJAX JSON, apenas admin)
        'capturar_pncp' => $editais->capturarPncp(),

        // Exportação CSV
        'exportar_csv'  => $editais->exportarCsv(filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'editais'),

        // Consulta CNPJ via ReceitaWS
        'consultar_cnpj' => $editais->consultarCnpj(filter_input(INPUT_GET, 'cnpj', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''),

        // Admin ações (AJAX / POST)
        'criar_usuario'  => (new AdminController())->criarUsuario(),
        'toggle_usuario' => (new AdminController())->toggleUsuario(),
        'limpar_logs'    => (new AdminController())->limparLogs(),
        'health_check'   => (new AdminController())->healthCheck(),

        default => (function () {
            http_response_code(404);
            echo json_encode(['erro' => 'Ação não encontrada.']);
            exit;
        })()
    };
    exit;
}

// ─── Rotas de página ─────────────────────────────────────────────────────────
if ($page === '' || $page === 'index') {
    $page = isset($_SESSION['usuario_id']) ? 'dashboard' : 'login';
}

match ($page) {
    'login'      => $auth->login(),
    'dashboard'  => $editais->listar(),
    'detalhes'   => $editais->detalhe($id),
    'relatorios' => $editais->relatorios(),

    'editais' => (function () use ($editais, $id) {
        $sub = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
        match ($sub) {
            'novo'   => $editais->formulario(null),
            'editar' => $editais->formulario($id),
            default  => (function () { header('Location: ?page=dashboard'); exit; })(),
        };
    })(),

    'modulos' => (function () use ($modulos, $id) {
        $sub = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
        match ($sub) {
            'novo'   => $modulos->formulario(null),
            'editar' => $modulos->formulario($id),
            default  => $modulos->listar(),
        };
    })(),

    'admin' => (new AdminController())->index(),

    default => (function () {
        AuthController::requerAutenticacao();
        http_response_code(404);
        $pageTitle = '404 — Página não encontrada';
        include __DIR__ . '/views/layout/header.php';
        echo '<div class="bg-white rounded-xl p-12 text-center shadow-sm border border-gray-100">
                <p class="text-6xl font-bold text-gray-200 mb-4">404</p>
                <p class="text-gray-500 mb-4">Página não encontrada.</p>
                <a href="?page=dashboard" class="text-blue-600 hover:underline text-sm">← Voltar ao Dashboard</a>
              </div>';
        include __DIR__ . '/views/layout/footer.php';
    })()
};
