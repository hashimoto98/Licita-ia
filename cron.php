<?php
/**
 * LicitAI — Cron Job de Automação
 *
 * Execute diariamente via crontab (Linux/cPanel) ou Agendador de Tarefas (Windows):
 *   0 7 * * * /usr/bin/php /var/www/licita-ia/cron.php >> /var/log/licita-ia-cron.log 2>&1
 *
 * Também pode ser acionado com token via web (apenas em dev):
 *   GET /licita-ia/cron.php?secret=SEU_CRON_SECRET&action=fetch
 *
 * @security Acesso web bloqueado pelo .htaccess em produção.
 *           Nunca exponha este arquivo publicamente sem autenticação.
 */

define('LICITA_CRON', true);

// ─── Bootstrap ────────────────────────────────────────────────────────────────
$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $secretRecebido = $_GET['secret'] ?? '';
    // Carrega config para verificar CRON_SECRET
    if (!file_exists(__DIR__ . '/config/config.php')) {
        http_response_code(500);
        exit("Config não encontrada.\n");
    }
    require_once __DIR__ . '/config/config.php';

    if (!hash_equals(CRON_SECRET, $secretRecebido)) {
        http_response_code(403);
        exit("Acesso negado.\n");
    }
    header('Content-Type: text/plain; charset=utf-8');
} else {
    require_once __DIR__ . '/config/config.php';
}

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/LogAuditoria.php';
require_once __DIR__ . '/models/Edital.php';
require_once __DIR__ . '/models/Modulo.php';
require_once __DIR__ . '/models/Usuario.php';
require_once __DIR__ . '/controllers/ApiPncpController.php';
require_once __DIR__ . '/controllers/OpenRouterController.php';
require_once __DIR__ . '/controllers/MatchController.php';

$log = new LogAuditoria();
$action = $isCli ? ($argv[1] ?? 'all') : ($_GET['action'] ?? 'all');
$dataInicial = $isCli ? ($argv[2] ?? date('Y-m-d')) : ($_GET['data_inicial'] ?? date('Y-m-d'));
$dataFinal   = $isCli ? ($argv[3] ?? date('Y-m-d')) : ($_GET['data_final']   ?? date('Y-m-d'));

function output(string $msg): void {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$msg}\n";
    flush();
}

output("=== LicitAI Cron — Ação: {$action} | Data: {$dataInicial} a {$dataFinal} ===");

// ─── AÇÃO: fetch — Captura novos editais do PNCP ─────────────────────────────
if ($action === 'fetch' || $action === 'all') {
    output("Iniciando captura PNCP...");

    $pncpCtrl  = new ApiPncpController();
    $resultado = $pncpCtrl->capturar($dataInicial, $dataFinal);

    output("Total API: {$resultado['total_api']} | Novos: {$resultado['novos_salvos']} | " .
           "Filtrados (não-TI): {$resultado['filtrados_ti']} | " .
           "Já existentes: {$resultado['ja_existentes']} | Erros: {$resultado['erros']}");

    $log->registrar('CRON_FETCH', "Novos: {$resultado['novos_salvos']} | Filtrados: {$resultado['filtrados_ti']}");
}

// ─── AÇÃO: analyze — Analisa editais pendentes com IA ───────────────────────
if ($action === 'analyze' || $action === 'all') {
    output("Iniciando análise de editais pendentes com IA...");

    $modelEdital = new Edital();
    $matchCtrl   = new MatchController();
    $pendentes   = $modelEdital->pendentesDeAnalise(10); // Máx 10 por execução de cron

    if (empty($pendentes)) {
        output("Nenhum edital pendente de análise.");
    } else {
        output(count($pendentes) . " editais na fila de análise.");

        foreach ($pendentes as $edital) {
            output("Analisando edital #{$edital['id']}: " . substr($edital['objeto'], 0, 60) . "...");

            $resultado = $matchCtrl->analisarEdital($edital['id']);

            if ($resultado['sucesso']) {
                output("  ✓ Match: {$resultado['porcentagem']}%");
            } else {
                output("  ✗ Erro: {$resultado['mensagem']}");
            }

            // Pausa entre chamadas de IA para respeitar rate limit da API
            sleep(2);
        }
    }

    $log->registrar('CRON_ANALYZE', "Editais processados: " . count($pendentes));
}

// ─── AÇÃO: cleanup — Limpeza de logs antigos ─────────────────────────────────
if ($action === 'cleanup' || ($action === 'all' && date('j') === '1')) {
    // Cleanup apenas no dia 1 de cada mês quando action=all
    output("Limpando logs de auditoria com mais de " . LOG_RETENTION_DAYS . " dias...");
    $removidos = $log->limparAntigos(LOG_RETENTION_DAYS);
    output("  {$removidos} registros de log removidos.");
    $log->registrar('CRON_CLEANUP', "Logs removidos: {$removidos}");
}

output("=== Cron finalizado. ===\n");
