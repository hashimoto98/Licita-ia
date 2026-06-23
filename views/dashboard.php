<?php
$pageTitle = 'Dashboard — Funil de Oportunidades';
include __DIR__ . '/layout/header.php';

if (!function_exists('matchBadge')) {
    function matchBadge(mixed $pct, string $status = ''): string {
        $pct = (float) $pct;
        if ($pct === 0.0 && $status !== 'analisado') return '<span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">Não analisado</span>';
        if ($pct >= MATCH_HIGH)   return "<span class=\"px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700 font-semibold\">{$pct}%</span>";
        if ($pct >= MATCH_MEDIUM) return "<span class=\"px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700 font-semibold\">{$pct}%</span>";
        return "<span class=\"px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 font-semibold\">{$pct}%</span>";
    }
}
if (!function_exists('matchBarColor')) {
    function matchBarColor(mixed $pct): string {
        $pct = (float) $pct;
        if ($pct >= MATCH_HIGH)   return 'bg-green-500';
        if ($pct >= MATCH_MEDIUM) return 'bg-yellow-400';
        return 'bg-red-400';
    }
}
if (!function_exists('valorBR')) {
    function valorBR(mixed $v): string {
        if ($v === null || $v === false || $v === '') return 'Não informado';
        return 'R$ ' . number_format((float) $v, 2, ',', '.');
    }
}
?>

<!-- ─── Cards de Estatísticas ───────────────────────────────────────────── -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
        ['label' => 'Total de Editais', 'valor' => $stats['total'], 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'cor' => 'blue'],
        ['label' => 'Analisados', 'valor' => $stats['analisados'], 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'cor' => 'indigo'],
        ['label' => 'Alta Compatibilidade (≥' . MATCH_HIGH . '%)', 'valor' => $stats['alta_comp'], 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', 'cor' => 'green'],
        ['label' => 'Valor Total Estimado', 'valor' => valorBR($stats['valor_total']), 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 6v1m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'cor' => 'emerald'],
    ];
    $cores = ['blue' => 'text-blue-600 bg-blue-50', 'indigo' => 'text-indigo-600 bg-indigo-50', 'green' => 'text-green-600 bg-green-50', 'emerald' => 'text-emerald-600 bg-emerald-50'];
    foreach ($cards as $card): ?>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100 card-hover">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg <?= $cores[$card['cor']] ?> flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $card['icon'] ?>"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-2xl font-bold text-gray-800"><?= htmlspecialchars((string) $card['valor']) ?></p>
                <p class="text-xs text-gray-500 mt-0.5 leading-tight"><?= htmlspecialchars($card['label']) ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ─── Gráficos ─────────────────────────────────────────────────────────── -->
<div class="grid lg:grid-cols-3 gap-4 mb-6">
    <!-- Gráfico de barras: Capturas por semana -->
    <div class="lg:col-span-2 bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-700 text-sm">Editais Capturados vs Compatíveis (últimas 8 semanas)</h2>
        </div>
        <div class="h-52">
            <canvas id="graficoSemanas"></canvas>
        </div>
    </div>

    <!-- Doughnut: Distribuição por modalidade -->
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <h2 class="font-semibold text-gray-700 text-sm mb-4">Distribuição por Modalidade</h2>
        <div class="h-52">
            <canvas id="graficoModalidade"></canvas>
        </div>
    </div>
</div>

<!-- ─── Filtros e Busca ──────────────────────────────────────────────────── -->
<div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="page" value="dashboard">

        <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
            <div class="relative">
                <input type="text" name="busca" value="<?= htmlspecialchars($filtros['busca']) ?>"
                       placeholder="Órgão ou objeto..."
                       class="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <svg class="absolute left-2.5 top-2.5 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Modalidade</label>
            <select name="modalidade" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Todas</option>
                <?php foreach ($modalidades as $mod): ?>
                <option value="<?= htmlspecialchars($mod) ?>" <?= $filtros['modalidade'] === $mod ? 'selected' : '' ?>>
                    <?= htmlspecialchars($mod) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Todos</option>
                <option value="novo"      <?= $filtros['status'] === 'novo'      ? 'selected' : '' ?>>Novo</option>
                <option value="analisado" <?= $filtros['status'] === 'analisado' ? 'selected' : '' ?>>Analisado</option>
                <option value="erro"      <?= $filtros['status'] === 'erro'      ? 'selected' : '' ?>>Erro</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Match mínimo (%)</label>
            <input type="number" name="match_min" min="0" max="100"
                   value="<?= htmlspecialchars($filtros['match_min']) ?>"
                   class="w-24 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="0">
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-700 text-white text-sm rounded-lg hover:bg-blue-800 transition-colors">
            Filtrar
        </button>
        <a href="?page=dashboard" class="px-4 py-2 text-gray-600 text-sm rounded-lg hover:bg-gray-100 transition-colors">
            Limpar
        </a>

        <?php if (($_SESSION['usuario_perfil'] ?? '') === 'admin'): ?>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" onclick="document.getElementById('modalPncp').classList.remove('hidden')"
                    class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Capturar do PNCP
            </button>
            <a href="?page=admin"
               class="px-4 py-2 text-gray-600 text-sm rounded-lg hover:bg-gray-100 transition-colors border border-gray-200">
                Admin
            </a>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- ─── Funil de Editais ─────────────────────────────────────────────────── -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-700 text-sm">
            Funil de Oportunidades
            <span class="ml-2 px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs font-normal">
                <?= $total ?> editais
            </span>
        </h2>
        <span class="text-xs text-gray-400">Ordenado por % de compatibilidade</span>
    </div>

    <?php if (empty($editais)): ?>
    <div class="py-16 text-center text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm">Nenhum edital encontrado com os filtros aplicados.</p>
        <p class="text-xs mt-1">Clique em "Capturar PNCP Hoje" para buscar novos editais.</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-50">
        <?php foreach ($editais as $idx => $edital):
            $pct = (float) ($edital['porcentagem_match'] ?? 0);
        ?>
        <div class="px-5 py-4 hover:bg-gray-50 transition-colors fade-in" style="animation-delay: <?= $idx * 0.04 ?>s">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">

                <!-- Ranking -->
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-500">
                    <?= $idx + 1 + (($pagina - 1) * ITEMS_PER_PAGE) ?>
                </div>

                <!-- Info principal -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <?= matchBadge($pct, $edital['status'] ?? '') ?>
                        <?php if ($edital['modalidade']): ?>
                        <span class="px-2 py-0.5 text-xs rounded-full bg-blue-50 text-blue-600">
                            <?= htmlspecialchars($edital['modalidade']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="px-2 py-0.5 text-xs rounded-full
                            <?= $edital['status'] === 'analisado' ? 'bg-indigo-50 text-indigo-600' :
                               ($edital['status'] === 'erro'      ? 'bg-red-50 text-red-600' :
                               ($edital['status'] === 'analisando'? 'bg-yellow-50 text-yellow-600' :
                                                                    'bg-gray-100 text-gray-500')) ?>">
                            <?= htmlspecialchars($edital['status']) ?>
                        </span>
                    </div>

                    <a href="?page=detalhes&id=<?= $edital['id'] ?>"
                       class="text-sm font-semibold text-gray-800 hover:text-blue-700 transition-colors line-clamp-2 block">
                        <?= htmlspecialchars($edital['objeto']) ?>
                    </a>

                    <div class="flex flex-wrap items-center gap-3 mt-1.5 text-xs text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <?= htmlspecialchars($edital['orgao']) ?>
                        </span>
                        <?php if ($edital['valor_estimado']): ?>
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 6v1m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <?= valorBR($edital['valor_estimado']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($edital['data_encerramento']): ?>
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Enc. <?= date('d/m/Y', strtotime($edital['data_encerramento'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Barra de match + ações -->
                <div class="sm:w-48 flex-shrink-0">
                    <?php if ($pct > 0): ?>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full match-bar <?= matchBarColor($pct) ?> rounded-full"
                                 style="width: <?= $pct ?>%"></div>
                        </div>
                        <span class="text-xs font-bold <?= $pct >= MATCH_HIGH ? 'text-green-600' : ($pct >= MATCH_MEDIUM ? 'text-yellow-600' : 'text-red-500') ?> w-9 text-right">
                            <?= $pct ?>%
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="flex gap-2">
                        <a href="?page=detalhes&id=<?= $edital['id'] ?>"
                           class="flex-1 text-center text-xs px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors font-medium">
                            Ver Detalhes
                        </a>
                        <?php if ($edital['status'] === 'novo' || $edital['status'] === 'erro'): ?>
                        <button onclick="analisarEdital(<?= $edital['id'] ?>, this)"
                                class="text-xs px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 transition-colors font-medium"
                                title="Analisar com IA">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <span class="text-xs text-gray-500">
            Página <?= $pagina ?> de <?= $totalPaginas ?> (<?= $total ?> editais)
        </span>
        <div class="flex gap-1">
            <?php if ($pagina > 1): ?>
            <a href="?page=dashboard&p=<?= $pagina - 1 ?>&<?= http_build_query(array_filter($filtros)) ?>"
               class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                ← Anterior
            </a>
            <?php endif; ?>
            <?php for ($p = max(1, $pagina - 2); $p <= min($totalPaginas, $pagina + 2); $p++): ?>
            <a href="?page=dashboard&p=<?= $p ?>&<?= http_build_query(array_filter($filtros)) ?>"
               class="px-3 py-1.5 text-xs rounded-lg transition-colors
                      <?= $p === $pagina ? 'bg-blue-700 text-white' : 'border border-gray-200 hover:bg-gray-50' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
            <?php if ($pagina < $totalPaginas): ?>
            <a href="?page=dashboard&p=<?= $pagina + 1 ?>&<?= http_build_query(array_filter($filtros)) ?>"
               class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                Próxima →
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ─── Modal Captura PNCP ────────────────────────────────────────────────── -->
<div id="modalPncp" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="font-semibold text-gray-800 text-base">Capturar Editais do PNCP</h3>
                <p class="text-xs text-gray-500 mt-0.5">Pré-filtra por setor e palavras-chave</p>
            </div>
            <button onclick="document.getElementById('modalPncp').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Data Inicial</label>
                    <input type="date" id="pncpDataInicial" value="<?= date('Y-m-d') ?>"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Data Final</label>
                    <input type="date" id="pncpDataFinal" value="<?= date('Y-m-d') ?>"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Setor</label>
                <select id="pncpSetor"
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
                <input type="number" id="pncpMaxPaginas" value="10" min="1" max="20"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="mt-5 flex gap-2">
            <button onclick="capturarPncp()" id="btnCapturarPncp"
                    class="flex-1 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 transition-colors font-medium">
                Capturar
            </button>
            <button onclick="document.getElementById('modalPncp').classList.add('hidden')"
                    class="px-4 py-2 text-gray-600 text-sm rounded-lg hover:bg-gray-100 border border-gray-200 transition-colors">
                Cancelar
            </button>
        </div>
        <div id="pncpResultado" class="mt-3 hidden text-xs text-gray-600 bg-gray-50 rounded-lg px-3 py-2 border border-gray-100"></div>
    </div>
</div>

<!-- Notificação flutuante -->
<div id="notif" class="fixed bottom-4 right-4 z-50 hidden">
    <div class="px-4 py-3 bg-gray-800 text-white text-sm rounded-xl shadow-lg flex items-center gap-3 max-w-xs">
        <div id="notifSpinner" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin hidden"></div>
        <span id="notifMsg"></span>
    </div>
</div>

<script>
// ─── Dados para Chart.js ───────────────────────────────────────────────────
const dadosSemanas = <?= json_encode($dadosGrafico['semanas'], JSON_UNESCAPED_UNICODE) ?>;
const dadosModalidades = <?= json_encode($dadosGrafico['modalidades'], JSON_UNESCAPED_UNICODE) ?>;

// Gráfico de barras
new Chart(document.getElementById('graficoSemanas'), {
    type: 'bar',
    data: {
        labels: dadosSemanas.map(d => 'Sem. ' + d.semana),
        datasets: [
            {
                label: 'Capturados',
                data: dadosSemanas.map(d => d.total),
                backgroundColor: 'rgba(59,130,246,0.7)',
                borderRadius: 4,
            },
            {
                label: 'Compatíveis (≥<?= MATCH_HIGH ?>%)',
                data: dadosSemanas.map(d => d.compativeis),
                backgroundColor: 'rgba(34,197,94,0.8)',
                borderRadius: 4,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f3f4f6' } },
            x: { grid: { display: false } }
        }
    }
});

// Gráfico de rosca
if (dadosModalidades.length > 0) {
    new Chart(document.getElementById('graficoModalidade'), {
        type: 'doughnut',
        data: {
            labels: dadosModalidades.map(d => d.modalidade),
            datasets: [{
                data: dadosModalidades.map(d => d.total),
                backgroundColor: ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981'],
                borderWidth: 2, borderColor: '#fff',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 12 } } },
            cutout: '60%',
        }
    });
} else {
    document.getElementById('graficoModalidade').parentElement.innerHTML =
        '<p class="text-center text-gray-400 text-sm pt-16">Sem dados de modalidade.</p>';
}

// ─── Helper: monta FormData com CSRF para todos os fetches protegidos ─────
function csrfForm(extra = {}) {
    const fd = new FormData();
    fd.append('csrf_token', window.CSRF_TOKEN || '');
    for (const [k, v] of Object.entries(extra)) fd.append(k, v);
    return fd;
}

// ─── Analisar edital com IA ───────────────────────────────────────────────
function analisarEdital(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>';
    mostrarNotif('Analisando edital #' + id + ' com IA...', true);

    fetch('?action=analisar_edital&id=' + id, { method: 'POST', body: csrfForm() })
        .then(r => r.json())
        .then(d => {
            if (d.sucesso) {
                mostrarNotif('✓ Match: ' + d.porcentagem + '%');
                setTimeout(() => location.reload(), 1500);
            } else {
                mostrarNotif('Erro: ' + d.mensagem);
                btn.disabled = false;
            }
        })
        .catch(() => {
            mostrarNotif('Erro de comunicação.');
            btn.disabled = false;
        });
}

// ─── Capturar PNCP ────────────────────────────────────────────────────────
function capturarPncp() {
    const btn = document.getElementById('btnCapturarPncp');
    const res = document.getElementById('pncpResultado');

    btn.disabled = true;
    btn.textContent = 'Capturando…';
    res.classList.add('hidden');
    mostrarNotif('Capturando editais do PNCP...', true);

    fetch('?action=capturar_pncp', {
        method: 'POST',
        body: csrfForm({
            data_inicial: document.getElementById('pncpDataInicial').value,
            data_final:   document.getElementById('pncpDataFinal').value,
            setor:        document.getElementById('pncpSetor').value,
            max_paginas:  document.getElementById('pncpMaxPaginas').value,
        })
    })
    .then(r => r.json())
    .then(d => {
        mostrarNotif(`✓ ${d.novos_salvos} novo(s) edital(is) — Setor: ${d.setor}`);
        res.classList.remove('hidden');
        res.innerHTML = `<b>${d.novos_salvos}</b> novos · <b>${d.ja_existentes}</b> já existentes · <b>${d.filtrados}</b> filtrados (total API: ${d.total_api})`;
        btn.disabled = false;
        btn.textContent = 'Capturar';
        if (d.novos_salvos > 0) {
            setTimeout(() => {
                document.getElementById('modalPncp').classList.add('hidden');
                location.reload();
            }, 2500);
        }
    })
    .catch(() => {
        mostrarNotif('Erro ao conectar ao PNCP.');
        btn.disabled = false;
        btn.textContent = 'Capturar';
    });
}

// ─── Notificação ─────────────────────────────────────────────────────────
function mostrarNotif(msg, loading = false) {
    const el = document.getElementById('notif');
    const sp = document.getElementById('notifSpinner');
    document.getElementById('notifMsg').textContent = msg;
    sp.classList.toggle('hidden', !loading);
    el.classList.remove('hidden');
    if (!loading) setTimeout(() => el.classList.add('hidden'), 4000);
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
