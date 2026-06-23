<?php
$tabAliases = ['captura' => 'config'];
$tabParam   = $_GET['tab'] ?? 'geral';
$activeTab  = $tabAliases[$tabParam] ?? $tabParam;
if (!in_array($activeTab, ['geral', 'usuarios', 'logs', 'config'], true)) {
    $activeTab = 'geral';
}
$csrfToken = $_SESSION['csrf_token'] ?? '';
include __DIR__ . '/../layout/header.php';
?>

<!-- ─── Navegação de abas ─────────────────────────────────────────────────── -->
<div class="mb-6">
    <nav class="flex gap-1 bg-gray-100 rounded-xl p-1" role="tablist">
        <?php
        $tabs = [
            'geral'    => ['label' => 'Visão Geral',       'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            'usuarios' => ['label' => 'Usuários',          'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
            'logs'     => ['label' => 'Logs de Auditoria', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            'config'   => ['label' => 'Config & Captura',  'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ];
        foreach ($tabs as $key => $tab):
            $isActive = $activeTab === $key;
        ?>
        <button type="button"
                role="tab"
                data-tab="<?= $key ?>"
                onclick="showTab('<?= $key ?>')"
                class="tab-btn flex items-center gap-1.5 px-4 py-2 text-sm rounded-lg font-medium transition-all
                       <?= $isActive ? 'bg-white shadow text-blue-700' : 'text-gray-500 hover:text-gray-700' ?>">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $tab['icon'] ?>"/>
            </svg>
            <span class="hidden sm:inline"><?= $tab['label'] ?></span>
        </button>
        <?php endforeach; ?>
    </nav>
</div>

<!-- Mensagens de feedback -->
<?php if (isset($_GET['ok'])): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700">
    <?= htmlspecialchars([
        'usuario_criado' => 'Usuário criado com sucesso.',
    ][$_GET['ok']] ?? 'Operação realizada com sucesso.') ?>
</div>
<?php elseif (isset($_GET['erro'])): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
    <?= htmlspecialchars([
        'campos_obrigatorios' => 'Preencha todos os campos obrigatórios.',
        'email_invalido'      => 'E-mail inválido.',
        'email_duplicado'     => 'Este e-mail já está cadastrado.',
    ][$_GET['erro']] ?? 'Ocorreu um erro. Tente novamente.') ?>
</div>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════════════════════════
     Tab: Visão Geral
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-geral" class="tab-pane <?= $activeTab !== 'geral' ? 'hidden' : '' ?>">

    <!-- Cards de resumo -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php
        $resumoCards = [
            ['label' => 'Usuários',   'valor' => $totalUsuarios, 'bg' => 'text-blue-600 bg-blue-50',   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Editais',    'valor' => $totalEditais,  'bg' => 'text-indigo-600 bg-indigo-50','icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['label' => 'Módulos',    'valor' => $totalModulos,  'bg' => 'text-purple-600 bg-purple-50','icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
            ['label' => 'Logs Total', 'valor' => $totalLogs,     'bg' => 'text-gray-600 bg-gray-100',  'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ];
        foreach ($resumoCards as $c): ?>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg <?= $c['bg'] ?> flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $c['icon'] ?>"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $c['valor'] ?></p>
                    <p class="text-xs text-gray-500 mt-0.5"><?= $c['label'] ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-4">
        <!-- Health Check -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-700 text-sm">Verificação do Sistema</h2>
                <button onclick="verificarSaude()" id="btnSaude"
                        class="text-xs px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors font-medium">
                    Verificar Agora
                </button>
            </div>
            <div id="resultadoSaude" class="space-y-2 min-h-[60px]">
                <p class="text-xs text-gray-400">Clique em "Verificar Agora" para testar as conexões.</p>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-50 space-y-1.5 text-xs text-gray-400">
                <div class="flex justify-between">
                    <span>Banco de dados</span>
                    <span class="font-mono"><?= htmlspecialchars($dbInfo['driver']) ?> — <?= htmlspecialchars($dbInfo['arquivo']) ?></span>
                </div>
                <?php if ($dbInfo['driver'] === 'SQLite'): ?>
                <div class="flex justify-between">
                    <span>Tamanho do banco</span>
                    <span><?= htmlspecialchars($dbInfo['tamanho']) ?></span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between">
                    <span>Modelo IA</span>
                    <span class="font-mono"><?= htmlspecialchars(OPENROUTER_MODEL) ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Versão</span>
                    <span><?= APP_VERSION ?></span>
                </div>
            </div>
        </div>

        <!-- Top ações -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <h2 class="font-semibold text-gray-700 text-sm mb-4">Ações Mais Frequentes</h2>
            <?php if (empty($topAcoes)): ?>
            <p class="text-xs text-gray-400">Nenhum log registrado ainda.</p>
            <?php else: ?>
            <?php $maxAcao = (int) ($topAcoes[0]['total'] ?? 1); ?>
            <div class="space-y-2.5">
                <?php foreach ($topAcoes as $acao): ?>
                <div>
                    <div class="flex justify-between text-xs mb-0.5">
                        <span class="font-mono text-gray-600"><?= htmlspecialchars($acao['acao']) ?></span>
                        <span class="font-semibold text-gray-700"><?= $acao['total'] ?></span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-400 rounded-full"
                             style="width: <?= round((int)$acao['total'] / $maxAcao * 100) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Atalhos rápidos -->
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <h2 class="font-semibold text-gray-700 text-sm mb-4">Ações Rápidas</h2>
        <div class="flex flex-wrap gap-3">
            <a href="?page=modulos"
               class="px-4 py-2 text-sm bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors border border-gray-200">
                Gerenciar Módulos
            </a>
            <a href="?page=relatorios"
               class="px-4 py-2 text-sm bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors border border-gray-200">
                Ver Relatórios
            </a>
            <a href="?action=exportar_csv&tipo=editais"
               class="px-4 py-2 text-sm bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors border border-gray-200">
                Exportar Editais CSV
            </a>
            <button onclick="showTab('usuarios')"
                    class="px-4 py-2 text-sm bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors border border-blue-100">
                Criar Usuário
            </button>
            <button onclick="showTab('config')"
                    class="px-4 py-2 text-sm bg-emerald-50 text-emerald-700 rounded-lg hover:bg-emerald-100 transition-colors border border-emerald-100">
                Captura PNCP Avançada
            </button>
            <button onclick="limparLogsAntigos()"
                    class="px-4 py-2 text-sm bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition-colors border border-red-100">
                Limpar Logs &gt;<?= LOG_RETENTION_DAYS ?>d
            </button>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     Tab: Usuários
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-usuarios" class="tab-pane <?= $activeTab !== 'usuarios' ? 'hidden' : '' ?>">
    <div class="grid lg:grid-cols-3 gap-4">

        <!-- Lista de usuários -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-700 text-sm">Usuários Cadastrados</h2>
                <span class="text-xs text-gray-400"><?= count($usuarios) ?> usuário(s)</span>
            </div>
            <div class="divide-y divide-gray-50">
                <?php foreach ($usuarios as $u):
                    $ehEu = (int) $u['id'] === (int) ($_SESSION['usuario_id'] ?? 0);
                ?>
                <div class="px-5 py-3 flex items-center gap-4">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold text-sm flex-shrink-0">
                        <?= htmlspecialchars(mb_strtoupper(mb_substr($u['nome'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($u['nome']) ?></p>
                            <?php if ($ehEu): ?>
                            <span class="text-xs text-blue-600 font-medium">(você)</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($u['email']) ?></p>
                        <?php if ($u['ultimo_acesso']): ?>
                        <p class="text-xs text-gray-400">
                            Último acesso: <?= date('d/m/Y H:i', strtotime($u['ultimo_acesso'])) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="px-2 py-0.5 text-xs rounded-full font-medium
                            <?= $u['perfil'] === 'admin' ? 'bg-purple-100 text-purple-700' :
                               ($u['perfil'] === 'analista' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') ?>">
                            <?= htmlspecialchars($u['perfil']) ?>
                        </span>
                        <button onclick="toggleUsuario(<?= $u['id'] ?>, this)"
                                data-ativo="<?= (int) $u['ativo'] ?>"
                                title="<?= $u['ativo'] ? 'Desativar' : 'Ativar' ?> usuário"
                                <?= $ehEu ? 'disabled title="Sua conta"' : '' ?>
                                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none
                                       <?= $u['ativo'] ? 'bg-green-500' : 'bg-gray-300' ?>
                                       <?= $ehEu ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' ?>">
                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform
                                         <?= $u['ativo'] ? 'translate-x-4' : 'translate-x-0.5' ?>"></span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($usuarios)): ?>
                <div class="px-5 py-8 text-center text-gray-400 text-sm">Nenhum usuário encontrado.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulário de criação -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 self-start">
            <h2 class="font-semibold text-gray-700 text-sm mb-4">Criar Novo Usuário</h2>
            <form method="POST" action="?action=criar_usuario" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome completo *</label>
                    <input type="text" name="nome" required autocomplete="off"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex.: João da Silva">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">E-mail *</label>
                    <input type="email" name="email" required autocomplete="off"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="email@empresa.com">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Senha *</label>
                    <input type="password" name="senha" required minlength="8" autocomplete="new-password"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Mínimo 8 caracteres">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Perfil</label>
                    <select name="perfil"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="analista">Analista — acesso completo às análises</option>
                        <option value="visualizador">Visualizador — somente leitura</option>
                        <option value="admin">Administrador — acesso total</option>
                    </select>
                </div>
                <button type="submit"
                        class="w-full py-2 bg-blue-700 text-white text-sm rounded-lg hover:bg-blue-800 transition-colors font-medium">
                    Criar Usuário
                </button>
            </form>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     Tab: Logs de Auditoria
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-logs" class="tab-pane <?= $activeTab !== 'logs' ? 'hidden' : '' ?>">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center gap-3">
            <h2 class="font-semibold text-gray-700 text-sm">Logs de Auditoria</h2>

            <!-- Filtro por ação -->
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="page"  value="admin">
                <input type="hidden" name="tab"   value="logs">
                <select name="filtro_acao"
                        onchange="this.form.submit()"
                        class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas as ações</option>
                    <?php foreach ($acoes as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>"
                            <?= ($filtroAcao ?? '') === $a ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <span class="text-xs text-gray-400 ml-auto"><?= count($logs) ?> registro(s) exibido(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-left border-b border-gray-100">
                        <th class="px-4 py-2.5 font-medium whitespace-nowrap">Data / Hora</th>
                        <th class="px-4 py-2.5 font-medium">Ação</th>
                        <th class="px-4 py-2.5 font-medium">Usuário</th>
                        <th class="px-4 py-2.5 font-medium">Descrição</th>
                        <th class="px-4 py-2.5 font-medium whitespace-nowrap">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($logs as $log): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-2.5 text-gray-500 font-mono whitespace-nowrap">
                            <?= date('d/m/Y H:i:s', strtotime($log['criado_em'])) ?>
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-mono font-medium">
                                <?= htmlspecialchars($log['acao']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">
                            <?= htmlspecialchars($log['usuario_nome'] ?? '—') ?>
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 max-w-xs truncate">
                            <?= htmlspecialchars($log['descricao'] ?? '') ?>
                        </td>
                        <td class="px-4 py-2.5 text-gray-400 font-mono whitespace-nowrap">
                            <?= htmlspecialchars($log['ip'] ?? '') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400">Nenhum log encontrado.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     Tab: Configurações & Captura PNCP
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-config" class="tab-pane <?= $activeTab !== 'config' ? 'hidden' : '' ?>">

    <!-- Keywords por setor -->
    <div class="grid lg:grid-cols-3 gap-4 mb-4">
        <?php
        $setoresKw = [
            'TI'         => ['kws' => TI_KEYWORDS,         'badge' => 'bg-blue-50 text-blue-700'],
            'Tributário' => ['kws' => TRIBUTARIO_KEYWORDS,  'badge' => 'bg-orange-50 text-orange-700'],
            'Educação'   => ['kws' => EDUCACAO_KEYWORDS,    'badge' => 'bg-green-50 text-green-700'],
        ];
        foreach ($setoresKw as $nome => $dados): ?>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-700 text-sm"><?= $nome ?></h3>
                <span class="text-xs px-2 py-0.5 rounded-full <?= $dados['badge'] ?>">
                    <?= count($dados['kws']) ?> palavras
                </span>
            </div>
            <div class="flex flex-wrap gap-1.5 max-h-48 overflow-y-auto pr-1">
                <?php foreach ($dados['kws'] as $kw): ?>
                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 font-mono">
                    <?= htmlspecialchars($kw) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Captura avançada -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center gap-3 mb-1">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <h3 class="font-semibold text-gray-700 text-sm">Captura Avançada do PNCP</h3>
        </div>
        <p class="text-xs text-gray-500 mb-5">
            Execute capturas com controle de setor, período e volume.
            Cada página da API retorna até 50 editais; o pré-filtro por palavras-chave seleciona
            apenas os relevantes para o setor escolhido.
        </p>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Data Inicial</label>
                <input type="date" id="adminDataInicial" value="<?= date('Y-m-d') ?>"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Data Final</label>
                <input type="date" id="adminDataFinal" value="<?= date('Y-m-d') ?>"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Setor</label>
                <select id="adminSetor"
                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="ti">Tecnologia da Informação</option>
                    <option value="tributario">Tributário / Fiscal</option>
                    <option value="educacao">Educação</option>
                    <option value="todos">Todos os Setores</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Máx. Páginas
                    <span class="font-normal text-gray-400">(50 editais/pág.)</span>
                </label>
                <input type="number" id="adminMaxPaginas" value="10" min="1" max="20"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="flex items-center gap-4 flex-wrap">
            <button onclick="capturarPncpAdmin()" id="btnCapturarAdmin"
                    class="px-5 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 transition-colors font-medium flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Iniciar Captura
            </button>
            <div id="resultadoCaptura" class="hidden text-xs text-gray-600 bg-gray-50 rounded-lg px-4 py-2 border border-gray-200"></div>
        </div>

        <!-- Tabela de referência de setores x palavras-chave -->
        <div class="mt-6 pt-5 border-t border-gray-100">
            <h4 class="text-xs font-medium text-gray-600 mb-3">Referência: Palavras-chave combinadas por setor selecionado</h4>
            <div class="grid sm:grid-cols-2 gap-3 text-xs text-gray-500">
                <div class="bg-blue-50 rounded-lg p-3">
                    <p class="font-semibold text-blue-700 mb-1">TI (<?= count(TI_KEYWORDS) ?> palavras)</p>
                    <p>Tecnologia da Informação pura — software, cloud, ERP, CRM…</p>
                </div>
                <div class="bg-orange-50 rounded-lg p-3">
                    <p class="font-semibold text-orange-700 mb-1">Tributário (<?= count(TI_KEYWORDS) + count(TRIBUTARIO_KEYWORDS) ?> palavras combinadas)</p>
                    <p>TI + SEFAZ, NF-e, ISSQN, IPTU, SPED, dívida ativa…</p>
                </div>
                <div class="bg-green-50 rounded-lg p-3">
                    <p class="font-semibold text-green-700 mb-1">Educação (<?= count(TI_KEYWORDS) + count(EDUCACAO_KEYWORDS) ?> palavras combinadas)</p>
                    <p>TI + SIGE, FNDE, merenda escolar, portal do aluno, ENEM…</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="font-semibold text-gray-700 mb-1">Todos (<?= count(array_unique(array_merge(TI_KEYWORDS, TRIBUTARIO_KEYWORDS, EDUCACAO_KEYWORDS))) ?> palavras únicas)</p>
                    <p>União completa dos três conjuntos — maior alcance, maior volume.</p>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Notificação flutuante -->
<div id="notifAdmin" class="fixed bottom-4 right-4 z-50 hidden">
    <div class="px-4 py-3 bg-gray-800 text-white text-sm rounded-xl shadow-lg flex items-center gap-3 max-w-sm">
        <div id="notifAdminSpinner" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin hidden flex-shrink-0"></div>
        <span id="notifAdminMsg"></span>
    </div>
</div>

<script>
const CSRF_ADMIN = <?= json_encode($csrfToken) ?>;

function csrfFormAdmin(extra = {}) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF_ADMIN);
    for (const [k, v] of Object.entries(extra)) fd.append(k, String(v));
    return fd;
}

function showTab(name) {
    document.querySelectorAll('.tab-pane').forEach(el => {
        el.classList.toggle('hidden', el.id !== 'tab-' + name);
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const active = btn.dataset.tab === name;
        btn.classList.toggle('bg-white',    active);
        btn.classList.toggle('shadow',      active);
        btn.classList.toggle('text-blue-700', active);
        btn.classList.toggle('text-gray-500', !active);
    });
    history.replaceState(null, '', '?page=admin&tab=' + name);
}

// Inicializa pela query string (suporta alias tab=captura → config)
(function () {
    const aliases = { captura: 'config' };
    const p = new URLSearchParams(location.search);
    const raw = p.get('tab') || 'geral';
    const t = aliases[raw] || raw;
    if (document.getElementById('tab-' + t)) showTab(t);
})();

function notifAdmin(msg, loading = false) {
    const el = document.getElementById('notifAdmin');
    document.getElementById('notifAdminMsg').textContent = msg;
    document.getElementById('notifAdminSpinner').classList.toggle('hidden', !loading);
    el.classList.remove('hidden');
    if (!loading) setTimeout(() => el.classList.add('hidden'), 4500);
}

function verificarSaude() {
    const btn = document.getElementById('btnSaude');
    const res = document.getElementById('resultadoSaude');
    btn.disabled = true;
    btn.textContent = 'Verificando…';
    res.innerHTML = '<p class="text-xs text-gray-400 animate-pulse">Testando conexões…</p>';

    fetch('?action=health_check')
        .then(r => r.json())
        .then(d => {
            const labels = { db: 'Banco de dados', openrouter: 'OpenRouter API', pncp: 'PNCP API' };
            res.innerHTML = Object.entries(d).map(([k, v]) => `
                <div class="flex items-center gap-2 text-xs">
                    <span class="w-2 h-2 rounded-full flex-shrink-0 ${v.ok ? 'bg-green-500' : 'bg-red-500'}"></span>
                    <span class="font-medium text-gray-700 w-28">${labels[k] ?? k}</span>
                    <span class="text-gray-500">${v.info}</span>
                </div>
            `).join('');
        })
        .catch(() => {
            res.innerHTML = '<p class="text-xs text-red-500">Erro ao verificar conexões.</p>';
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Verificar Agora';
        });
}

function toggleUsuario(id, btn) {
    btn.disabled = true;
    fetch('?action=toggle_usuario', { method: 'POST', body: csrfFormAdmin({ id }) })
        .then(r => r.json())
        .then(d => {
            if (!d.sucesso) { notifAdmin(d.mensagem || 'Erro.'); return; }
            const ativo = d.novo_status;
            btn.classList.toggle('bg-green-500', ativo);
            btn.classList.toggle('bg-gray-300',  !ativo);
            const span = btn.querySelector('span');
            span.classList.toggle('translate-x-4',   ativo);
            span.classList.toggle('translate-x-0.5', !ativo);
            notifAdmin(ativo ? 'Usuário ativado.' : 'Usuário desativado.');
        })
        .catch(() => notifAdmin('Erro de comunicação.'))
        .finally(() => btn.disabled = false);
}

function limparLogsAntigos() {
    if (!confirm('Remover logs com mais de <?= LOG_RETENTION_DAYS ?> dias?\nEsta ação não pode ser desfeita.')) return;
    notifAdmin('Limpando logs antigos…', true);
    fetch('?action=limpar_logs', { method: 'POST', body: csrfFormAdmin({ dias: <?= LOG_RETENTION_DAYS ?> }) })
        .then(r => r.json())
        .then(d => notifAdmin(`${d.removidos} log(s) removido(s).`))
        .catch(() => notifAdmin('Erro ao limpar logs.'));
}

function capturarPncpAdmin() {
    const btn = document.getElementById('btnCapturarAdmin');
    const res = document.getElementById('resultadoCaptura');

    btn.disabled = true;
    res.classList.add('hidden');
    notifAdmin('Capturando editais do PNCP…', true);

    fetch('?action=capturar_pncp', {
        method: 'POST',
        body: csrfFormAdmin({
            data_inicial: document.getElementById('adminDataInicial').value,
            data_final:   document.getElementById('adminDataFinal').value,
            setor:        document.getElementById('adminSetor').value,
            max_paginas:  document.getElementById('adminMaxPaginas').value,
        })
    })
    .then(r => r.json())
    .then(d => {
        notifAdmin(`✓ ${d.novos_salvos} novo(s) edital(is) — ${d.setor}`);
        res.classList.remove('hidden');
        res.innerHTML = `
            <b>${d.novos_salvos}</b> novos &nbsp;·&nbsp;
            <b>${d.ja_existentes}</b> já existentes &nbsp;·&nbsp;
            <b>${d.filtrados}</b> filtrados &nbsp;·&nbsp;
            <b>${d.total_api}</b> total na API
            ${d.erros > 0 ? `&nbsp;·&nbsp;<span class="text-red-600"><b>${d.erros}</b> erro(s)</span>` : ''}
        `;
    })
    .catch(() => notifAdmin('Erro ao conectar ao PNCP.'))
    .finally(() => {
        btn.disabled = false;
    });
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
