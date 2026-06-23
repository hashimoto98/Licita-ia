<?php
/**
 * LicitAI - Configurações Globais da Aplicação
 * Carregue variáveis sensíveis via variáveis de ambiente em produção.
 */

// ─── Carregador de .env ───────────────────────────────────────────────────────
$_envFile = __DIR__ . '/../.env';
if (is_file($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_linha) {
        if ($_linha[0] === '#' || !str_contains($_linha, '=')) continue;
        [$_k, $_v] = explode('=', $_linha, 2);
        $_k = trim($_k); $_v = trim($_v);
        if ($_k !== '') {
            $_ENV[$_k] = $_v;
            if (!getenv($_k)) @putenv("{$_k}={$_v}");
        }
    }
}
unset($_envFile, $_linha, $_k, $_v);

// Lê variável de ambiente com fallback para $_ENV (compatível com putenv desabilitado)
function _env(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    return $_ENV[$key] ?? $default;
}

// ─── Fuso horário ────────────────────────────────────────────────────────────
date_default_timezone_set(_env('APP_TIMEZONE', 'America/Sao_Paulo'));

// ─── Banco de Dados ────────────────────────────────────────────────────────────
define('DB_DRIVER',      _env('DB_DRIVER',      'sqlite'));
define('DB_SQLITE_PATH', _env('DB_SQLITE_PATH', __DIR__ . '/../storage/licita_ia.sqlite'));
define('DB_HOST',        _env('DB_HOST',        'localhost'));
define('DB_NAME',        _env('DB_NAME',        'licita_ia'));
define('DB_USER',        _env('DB_USER',        'root'));
define('DB_PASS',        _env('DB_PASS',        ''));
define('DB_CHARSET', 'utf8mb4');

// ─── IA / OpenRouter ──────────────────────────────────────────────────────────
define('OPENROUTER_API_KEY', _env('OPENROUTER_API_KEY', ''));
define('OPENROUTER_API_URL', _env('OPENROUTER_API_URL', 'https://openrouter.ai/api/v1/chat/completions'));
define('OPENROUTER_MODEL',   _env('OPENROUTER_MODEL',   'anthropic/claude-haiku-4-5-20251001'));

// ─── APIs Governamentais ──────────────────────────────────────────────────────
define('PNCP_API_URL',    'https://pncp.gov.br/api/pncp/v1');
define('PNCP_SEARCH_URL', 'https://pncp.gov.br/api/search/');
define('RECEITA_WS_URL',  'https://www.receitaws.com.br/v1/cnpj');

// ─── Aplicação ────────────────────────────────────────────────────────────────
define('APP_NAME',    'LicitAI');
define('APP_VERSION', '1.0.0');
define('APP_URL',     _env('APP_URL',     'http://localhost/licita-ia'));
define('APP_SECRET',  _env('APP_SECRET',  'defina-APP_SECRET-no-ambiente'));
define('CRON_SECRET', _env('CRON_SECRET', 'troque-este-segredo-do-cron'));

// ─── Sessão ───────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);
define('SESSION_NAME',     'licita_ai_sess');

// ─── Retenção de logs de auditoria (Lei Carolina Dieckmann) ──────────────────
define('LOG_RETENTION_DAYS', 365);

// ─── Thresholds de compatibilidade (%) ───────────────────────────────────────
define('MATCH_HIGH',   70);
define('MATCH_MEDIUM', 40);

// ─── Paginação ────────────────────────────────────────────────────────────────
define('ITEMS_PER_PAGE', 20);

// ─── Palavras-chave por setor para pré-filtro PNCP ───────────────────────────

define('TI_KEYWORDS', [
    'tecnologia da informação', 'software', 'sistema', 'aplicativo',
    'plataforma', 'licença de software', 'solução tecnológica',
    'desenvolvimento de software', 'suporte técnico', 'infraestrutura de ti',
    'datacenter', 'nuvem', 'cloud', 'segurança da informação',
    'erp', 'crm', 'gestão de ti', 'serviços de ti', 'manutenção de software',
    'help desk', 'service desk', 'banco de dados', 'rede de computadores',
    'virtualização', 'backup', 'firewall', 'antivírus', 'api', 'integração de sistemas',
    'implantação de sistema', 'migração de dados', 'outsourcing de ti',
]);

define('TRIBUTARIO_KEYWORDS', [
    'tributário', 'tributária', 'tributos', 'arrecadação', 'fiscal',
    'sefaz', 'receita estadual', 'receita municipal', 'receita federal',
    'iss', 'issqn', 'iptu', 'itr', 'ipva', 'icms', 'irpf', 'irpj', 'cofins', 'pis',
    'nota fiscal eletrônica', 'nf-e', 'nfs-e', 'nfce', 'ct-e', 'mdf-e',
    'pgdas', 'simples nacional', 'sped', 'ecf', 'ecd', 'reinf',
    'dctf', 'pgfn', 'darf', 'certidão negativa de débitos',
    'execução fiscal', 'dívida ativa', 'cobrança fiscal',
    'dam', 'dte', 'domicílio tributário eletrônico',
    'siat', 'issweb', 'aiim', 'auto de infração', 'fiscalização tributária',
    'cadastro mobiliário', 'cadastro imobiliário', 'alvará', 'habite-se',
]);

define('EDUCACAO_KEYWORDS', [
    'educação', 'educacional', 'escolar', 'escola', 'ensino',
    'sige', 'saga', 'sistema de gestão escolar', 'matrícula', 'secretaria escolar',
    'merenda escolar', 'alimentação escolar', 'pnae',
    'transporte escolar', 'pnte',
    'ead', 'ensino a distância', 'plataforma de aprendizagem', 'lms', 'moodle',
    'portal do aluno', 'portal do professor', 'diário eletrônico', 'boletim escolar',
    'inep', 'censo escolar', 'saeb', 'enem', 'enade', 'provinha brasil',
    'fnde', 'pdde', 'fundeb', 'salário educação',
    'creche', 'pré-escola', 'educação infantil', 'ensino fundamental', 'ensino médio',
    'universidade', 'instituto federal', 'ifes', 'cefet', 'pronatec',
    'biblioteca escolar', 'laboratório de informática educacional',
    'avaliação institucional', 'gestão pedagógica',
]);
