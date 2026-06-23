<?php
/**
 * LicitAI - Relatórios e Exportação de Dados
 *
 * Oferece visualizações consolidadas e exportação em CSV dos dados do sistema.
 */
$pageTitle = 'Relatórios — ' . APP_NAME;
include __DIR__ . '/layout/header.php';
?>

<!-- Header da página -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h2 class="text-base font-semibold text-gray-800">Relatórios e Exportação</h2>
        <p class="text-xs text-gray-500 mt-0.5">Dados consolidados do sistema · Exportação em CSV compatível com Excel</p>
    </div>
    <div class="flex gap-2">
        <a href="?action=exportar_csv&tipo=editais"
           class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Exportar Editais CSV
        </a>
        <a href="?action=exportar_csv&tipo=modulos"
           class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Exportar Módulos CSV
        </a>
    </div>
</div>

<!-- ─── Cards de KPI ────────────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php
    $kpis = [
        ['label' => 'Total de Editais', 'val' => $stats['total'],           'sub' => 'capturados no sistema',     'cor' => 'blue',   'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['label' => 'Analisados',       'val' => $stats['analisados'],      'sub' => 'com análise IA concluída',  'cor' => 'indigo', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['label' => 'Alta Compat.',     'val' => $stats['alta_comp'],       'sub' => '≥' . MATCH_HIGH . '% de match', 'cor' => 'green',  'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
        ['label' => 'Pendentes',        'val' => $stats['pendentes'],       'sub' => 'aguardando análise IA',     'cor' => 'yellow', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    $corMap = ['blue'=>'text-blue-600 bg-blue-50','indigo'=>'text-indigo-600 bg-indigo-50','green'=>'text-green-600 bg-green-50','yellow'=>'text-yellow-600 bg-yellow-50'];
    foreach ($kpis as $k): ?>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg flex-shrink-0 flex items-center justify-center <?= $corMap[$k['cor']] ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $k['icon'] ?>"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800"><?= number_format((int)$k['val']) ?></p>
                <p class="text-xs font-medium text-gray-600"><?= $k['label'] ?></p>
                <p class="text-xs text-gray-400"><?= $k['sub'] ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ─── Gráficos Avançados ──────────────────────────────────────────────────── -->
<div class="grid lg:grid-cols-2 gap-5 mb-6">

    <!-- Distribuição por faixa de match -->
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <h3 class="font-semibold text-gray-700 text-sm mb-4">Distribuição por Faixa de Compatibilidade</h3>
        <div class="h-56">
            <canvas id="graficoFaixas"></canvas>
        </div>
    </div>

    <!-- Timeline de capturas -->
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <h3 class="font-semibold text-gray-700 text-sm mb-4">Evolução Mensal de Editais</h3>
        <div class="h-56">
            <canvas id="graficoMensal"></canvas>
        </div>
    </div>
</div>

<!-- ─── Tabela: Top 20 Editais por Match ────────────────────────────────────── -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-700 text-sm">
            Top <?= count($topEditais) ?> Editais por Compatibilidade
        </h3>
        <span class="text-xs text-gray-400">Apenas editais analisados</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left">
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Órgão</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Objeto</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Modalidade</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Valor</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Match</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Encerramento</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($topEditais)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                        Nenhum edital analisado ainda. Capture e analise editais no Dashboard.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($topEditais as $idx => $e):
                    $pct = (float)$e['porcentagem_match'];
                    $corPct = $pct >= MATCH_HIGH ? 'text-green-700 bg-green-100' : ($pct >= MATCH_MEDIUM ? 'text-yellow-700 bg-yellow-100' : 'text-red-700 bg-red-100');
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-400 font-mono text-xs"><?= $idx + 1 ?></td>
                    <td class="px-4 py-3">
                        <span class="text-gray-700 text-xs"><?= htmlspecialchars(mb_substr($e['orgao'], 0, 35)) ?><?= mb_strlen($e['orgao']) > 35 ? '…' : '' ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="?page=detalhes&id=<?= $e['id'] ?>" class="text-blue-600 hover:underline text-xs">
                            <?= htmlspecialchars(mb_substr($e['objeto'], 0, 50)) ?><?= mb_strlen($e['objeto']) > 50 ? '…' : '' ?>
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-gray-500 text-xs"><?= htmlspecialchars($e['modalidade'] ?? '—') ?></span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                        <?= $e['valor_estimado'] ? 'R$ ' . number_format((float)$e['valor_estimado'], 0, ',', '.') : '—' ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $corPct ?>">
                            <?= $pct ?>%
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs whitespace-nowrap
                               <?= isset($e['data_encerramento']) && strtotime($e['data_encerramento']) < time() ? 'text-red-500' : 'text-gray-600' ?>">
                        <?= isset($e['data_encerramento']) ? date('d/m/Y', strtotime($e['data_encerramento'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ─── Tabela: Módulos e cobertura ────────────────────────────────────────── -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-700 text-sm">Módulos Mais Solicitados em Editais</h3>
        <p class="text-xs text-gray-400 mt-0.5">Frequência com que cada módulo aparece nos editais analisados</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left">
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Módulo</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Categoria</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Palavras-chave</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($modulos as $m):
                    $kw = json_decode($m['palavras_chave'] ?? '[]', true) ?: [];
                ?>
                <tr class="hover:bg-gray-50 transition-colors <?= !$m['ativo'] ? 'opacity-60' : '' ?>">
                    <td class="px-4 py-3">
                        <span class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($m['nome']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500"><?= htmlspecialchars($m['categoria'] ?? '—') ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 text-xs rounded-full font-medium
                                     <?= $m['ativo'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                            <?= $m['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (array_slice($kw, 0, 4) as $k): ?>
                            <span class="px-1.5 py-0.5 bg-blue-50 text-blue-600 text-xs rounded border border-blue-100">
                                <?= htmlspecialchars($k) ?>
                            </span>
                            <?php endforeach; ?>
                            <?php if (count($kw) > 4): ?>
                            <span class="text-xs text-gray-400">+<?= count($kw) - 4 ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const faixasData = <?= json_encode($faixas, JSON_UNESCAPED_UNICODE) ?>;
const mensalData = <?= json_encode($mensal,  JSON_UNESCAPED_UNICODE) ?>;

// Gráfico de faixas de compatibilidade
new Chart(document.getElementById('graficoFaixas'), {
    type: 'bar',
    data: {
        labels: ['Não analisado', 'Baixa (< <?= MATCH_MEDIUM ?>%)', 'Média (<?= MATCH_MEDIUM ?>–<?= MATCH_HIGH - 1 ?>%)', 'Alta (≥ <?= MATCH_HIGH ?>%)'],
        datasets: [{
            label: 'Editais',
            data: [faixasData.nao_analisado, faixasData.baixa, faixasData.media, faixasData.alta],
            backgroundColor: ['#d1d5db', '#fca5a5', '#fde68a', '#86efac'],
            borderColor:     ['#9ca3af', '#ef4444', '#f59e0b', '#22c55e'],
            borderWidth: 1.5,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f3f4f6' } },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});

// Gráfico mensal
new Chart(document.getElementById('graficoMensal'), {
    type: 'line',
    data: {
        labels: mensalData.map(d => d.mes),
        datasets: [
            {
                label: 'Capturados',
                data: mensalData.map(d => d.total),
                borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)',
                tension: 0.4, fill: true, pointRadius: 4,
            },
            {
                label: 'Alta compat. (≥<?= MATCH_HIGH ?>%)',
                data: mensalData.map(d => d.alta_comp),
                borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)',
                tension: 0.4, fill: true, pointRadius: 4,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f3f4f6' } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
