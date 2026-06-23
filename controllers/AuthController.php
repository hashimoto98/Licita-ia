<?php
/**
 * LicitAI - Controller de Autenticação
 *
 * Implementa login seguro com bcrypt, proteção contra força bruta via
 * rate-limiting por IP em sessão, registro de IP e logs de auditoria.
 */
class AuthController
{
    private Usuario $modelUsuario;
    private LogAuditoria $log;

    public function __construct()
    {
        $this->modelUsuario = new Usuario();
        $this->log          = new LogAuditoria();
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            include __DIR__ . '/../views/auth/login.php';
            return;
        }

        // Validação do CSRF token
        $this->verificarCsrf();

        $email  = trim($_POST['email']  ?? '');
        $senha  = trim($_POST['senha']  ?? '');
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate limiting: máx. 5 tentativas em 15 minutos por IP
        $tentativas = $_SESSION['tentativas_login'][$ip] ?? 0;
        $bloqueioAte = $_SESSION['bloqueio_login'][$ip] ?? 0;

        if ($bloqueioAte > time()) {
            $restante = ceil(($bloqueioAte - time()) / 60);
            $this->redirecionarLogin("Muitas tentativas. Tente novamente em {$restante} minuto(s).");
            return;
        }

        if (empty($email) || empty($senha)) {
            $this->redirecionarLogin('Preencha todos os campos.');
            return;
        }

        $usuario = $this->modelUsuario->findByEmail($email);

        // Verifica credenciais — password_verify previne timing attacks
        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $tentativas++;
            $_SESSION['tentativas_login'][$ip] = $tentativas;

            if ($tentativas >= 5) {
                $_SESSION['bloqueio_login'][$ip] = time() + 900; // 15 min
                $_SESSION['tentativas_login'][$ip] = 0;
            }

            $this->log->registrar('LOGIN_FALHA', "Email: {$email}", null, $ip);
            $this->redirecionarLogin('Credenciais inválidas.');
            return;
        }

        // Login bem-sucedido: regenera sessão (Session Fixation prevention)
        $_SESSION['tentativas_login'][$ip] = 0;

        // "Lembrar-me": estende o cookie de sessão para 30 dias
        $lembrar = !empty($_POST['lembrar_me']);
        if ($lembrar) {
            $params = session_get_cookie_params();
            session_set_cookie_params(
                2_592_000,           // 30 dias em segundos
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_regenerate_id(true);

        $_SESSION['usuario_id']    = $usuario['id'];
        $_SESSION['usuario_nome']  = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_perfil']= $usuario['perfil'];
        $_SESSION['login_time']    = time();
        $_SESSION['lembrar_me']    = $lembrar;
        $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

        $this->modelUsuario->registrarAcesso($usuario['id'], $ip);
        $this->log->registrar('LOGIN_OK', "Login bem-sucedido", $usuario['id'], $ip);

        header('Location: ?page=dashboard');
        exit;
    }

    public function logout(): void
    {
        $usuarioId = $_SESSION['usuario_id'] ?? null;
        $this->log->registrar('LOGOUT', '', $usuarioId);

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        header('Location: ?page=login&msg=saiu');
        exit;
    }

    /** Garante que o usuário está autenticado; redireciona se não estiver. */
    public static function requerAutenticacao(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        // Expiração por inatividade — ignorada quando "Lembrar-me" está ativo (30 dias via cookie)
        if (empty($_SESSION['lembrar_me'])) {
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
                session_destroy();
                header('Location: ?page=login&msg=expirou');
                exit;
            }
        }
        $_SESSION['login_time'] = time(); // Renova o timer
    }

    /** Garante que o usuário tem perfil admin. */
    public static function requerAdmin(): void
    {
        self::requerAutenticacao();
        if (($_SESSION['usuario_perfil'] ?? '') !== 'admin') {
            http_response_code(403);
            include __DIR__ . '/../views/errors/403.php';
            exit;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function verificarCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        $esperado = $_SESSION['csrf_token'] ?? '';
        if (!$esperado || !hash_equals($esperado, $token)) {
            http_response_code(403);
            die('Token CSRF inválido. Recarregue a página e tente novamente.');
        }
    }

    private function redirecionarLogin(string $erro): void
    {
        $_SESSION['login_erro'] = $erro;
        header('Location: ?page=login');
        exit;
    }

    /** Gera (ou retorna o existente) CSRF token para os formulários. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
