<?php
$pageTitle = 'Detalhe do Edital — ' . APP_NAME;
include __DIR__ . '/layout/header.php';

$pct = (float) ($edital['porcentagem_match'] ?? 0);
$statusAnalise = $edital['status'] ?? 'novo';

function matchCircleColor(float $pct): string {
    if ($pct >= MATCH_HIGH)   return '#22c55e'; // verde
    if ($pct >= MATCH_MEDIUM) return '#f59e0b'; // amarelo
    if ($pct > 0)             return '#ef4444'; // vermelho
    return '#d1d5db';
}

$circlePct = min(100, $pct);
$circumference = 2 * M_PI * 44; // raio 44
$offset = $circumference * (1 - $circlePct / 100);
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-xs text-gray-500 mb-4">
    <a href="?page=dashboard" class="hover:text-blue-600 transition-colors">Dashboard</a>
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-700 truncate max-w-xs"><?= htmlspecialchars(substr($edital['objeto'], 0, 60)) ?>...</span>
</nav>

<div class="grid lg:grid-cols-3 gap-6">

    <!-- ─── Coluna esquerda: info + gauge ─────────────────────────────── -->
    <div class="lg:col-span-1 space-y-4">

        <!-- Card de compatibilidade -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 text-center">
            <p class="text-sm font-semibold text-gray-600 mb-3">Compatibilidade com o Portfólio</p>

            <!-- Gauge SVG -->
            <div class="relative inline-flex items-center justify-center w-32 h-32 mx-auto mb-3">
                <svg class="absolute" width="120" height="120" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="44" fill="none" stroke="#f3f4f6" stroke-width="10"/>
                    <circle cx="60" cy="60" r="44" fill="none"
                            stroke="<?= matchCircleColor($pct) ?>"
                            stroke-width="10"
                            stroke-dasharray="<?= $circumference ?>"
                            stroke-dashoffset="<?= $offset ?>"
                            stroke-linecap="round"
                            transform="rotate(-90 60 60)"
                            style="transition: stroke-dashoffset 1s ease-out"/>
                </svg>
                <div class="z-10 text-center">
                    <span class="text-2xl font-bold text-gray-800"><?= ($pct > 0 || $statusAnalise === 'analisado') ? $pct . '%' : '—' ?></span>
                    <?php if ($pct >= MATCH_HIGH): ?>
                    <span class="block text-xs text-green-600 font-medium">ALTA</span>
                    <?php elseif ($pct >= MATCH_MEDIUM): ?>
                    <span class="block text-xs text-yellow-600 font-medium">MÉDIA</span>
                    <?php elseif ($pct > 0 || $statusAnalise === 'analisado'): ?>
                    <span class="block text-xs text-red-500 font-medium">BAIXA</span>
                    <?php else: ?>
                    <span class="block text-xs text-gray-400">Pendente</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($itensMatch['total_matched'])): ?>
            <p class="text-xs text-gray-500">
                <?= $itensMatch['total_matched'] ?> de <?= $itensMatch['total_modulos'] ?> módulos cobertos
            </p>
            <?php endif; ?>

            <?php if ($statusAnalise === 'novo' || $statusAnalise === 'erro'): ?>
            <button onclick="analisarAgora(<?= $edital['id'] ?>)" id="btnAnalisar"
                    class="mt-4 w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm
                           font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                Analisar com IA
            </button>
            <?php endif; ?>

            <?php if ($statusAnalise === 'analisando'): ?>
            <div class="mt-4 py-2 text-yellow-600 text-sm flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Análise em andamento...
            </div>
            <?php endif; ?>
        </div>

        <!-- Dados do edital -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <h3 class="font-semibold text-gray-700 text-sm mb-3">Dados do Edital</h3>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Órgão</dt>
                    <dd class="text-gray-800 font-medium mt-0.5"><?= htmlspecialchars($edital['orgao']) ?></dd>
                </div>
                <?php if ($edital['modalidade']): ?>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Modalidade</dt>
                    <dd class="text-gray-800 mt-0.5"><?= htmlspecialchars($edital['modalidade']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($edital['valor_estimado']): ?>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Valor Estimado</dt>
                    <dd class="text-gray-800 font-semibold text-green-700 mt-0.5">
                        R$ <?= number_format((float) $edital['valor_estimado'], 2, ',', '.') ?>
                    </dd>
                </div>
                <?php endif; ?>
                <?php if ($edital['data_publicacao']): ?>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Publicação</dt>
                    <dd class="text-gray-800 mt-0.5"><?= date('d/m/Y', strtotime($edital['data_publicacao'])) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($edital['data_encerramento']): ?>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Encerramento</dt>
                    <dd class="text-gray-800 font-semibold mt-0.5
                               <?= strtotime($edital['data_encerramento']) < time() ? 'text-red-600' : 'text-orange-600' ?>">
                        <?= date('d/m/Y', strtotime($edital['data_encerramento'])) ?>
                        <?php $diasRestantes = (int) ceil((strtotime($edital['data_encerramento']) - time()) / 86400);
                        if ($diasRestantes > 0): ?>
                        <span class="text-xs ml-1">(<?= $diasRestantes ?> dias)</span>
                        <?php elseif ($diasRestantes <= 0): ?>
                        <span class="text-xs ml-1 text-red-500">(encerrado)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php endif; ?>
                <?php if ($edital['pncp_id']): ?>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">ID PNCP</dt>
                    <dd class="text-gray-600 text-xs mt-0.5 font-mono"><?= htmlspecialchars($edital['pncp_id']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($edital['link_edital']): ?>
                <div>
                    <a href="<?= htmlspecialchars($edital['link_edital']) ?>" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-xs font-medium transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        Acessar edital original
                    </a>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Upload de Termo de Referência -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                <h3 class="font-semibold text-gray-700 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Upload do Termo de Referência
                </h3>
            </div>
            <div class="p-4">
                <div id="dropZone"
                     class="border-2 border-dashed border-gray-200 rounded-xl p-5 text-center cursor-pointer
                            hover:border-blue-400 hover:bg-blue-50 transition-all"
                     onclick="document.getElementById('arquivoTR').click()"
                     ondragover="event.preventDefault(); this.classList.add('border-blue-400','bg-blue-50')"
                     ondragleave="this.classList.remove('border-blue-400','bg-blue-50')"
                     ondrop="handleDrop(event)">
                    <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-xs text-gray-500">Arraste o arquivo ou <span class="text-blue-600 font-medium">clique para selecionar</span></p>
                    <p class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX, TXT · máx. 10 MB</p>
                    <p id="nomeArquivo" class="text-xs text-blue-700 font-medium mt-2 hidden"></p>
                </div>
                <input type="file" id="arquivoTR" class="hidden"
                       accept=".pdf,.doc,.docx,.txt"
                       onchange="selecionarArquivo(this)">
                <button id="btnUpload" onclick="enviarUpload(<?= $edital['id'] ?>)"
                        class="mt-3 w-full py-2 px-4 bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold
                               rounded-lg transition-colors disabled:opacity-50 hidden">
                    Enviar e Analisar com IA
                </button>
                <div id="uploadProgress" class="mt-2 hidden">
                    <div class="flex items-center gap-2 text-xs text-blue-600">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span id="uploadStatus">Enviando arquivo...</span>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    O sistema extrairá o texto, enviará para a IA e calculará o percentual de match automaticamente.
                </p>
            </div>
        </div>

        <!-- Editar dados do edital -->
        <div class="flex gap-2">
            <a href="?page=editais&action=editar&id=<?= $edital['id'] ?>"
               class="flex-1 text-center text-xs px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600
                      rounded-lg transition-colors font-medium">
                ✏️ Editar Dados
            </a>
            <?php if (($_SESSION['usuario_perfil'] ?? '') === 'admin'): ?>
            <form method="POST" action="?action=excluir_edital&id=<?= $edital['id'] ?>"
                  onsubmit="return confirm('Excluir este edital permanentemente?')"
                  class="flex-1">
                <input type="hidden" name="csrf_token" value="<?= AuthController::csrfToken() ?>">
                <button type="submit"
                        class="w-full text-xs px-3 py-2 bg-red-50 hover:bg-red-100 text-red-600
                               rounded-lg transition-colors font-medium">
                    🗑 Excluir
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Resumo da IA -->
        <?php if (!empty($requisitosIa['resumo'])): ?>
        <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
            <div class="flex items-center gap-2 mb-2">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span class="text-xs font-semibold text-indigo-700">Resumo da IA</span>
            </div>
            <p class="text-sm text-indigo-800 leading-relaxed"><?= htmlspecialchars($requisitosIa['resumo']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── Coluna direita: checklist de match ─────────────────────────── -->
    <div class="lg:col-span-2 space-y-4">

        <!-- Objeto do edital -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <h3 class="font-semibold text-gray-700 text-sm mb-2">Objeto da Licitação</h3>
            <p class="text-gray-800 text-sm leading-relaxed"><?= htmlspecialchars($edital['objeto']) ?></p>
        </div>

        <?php if ($itensMatch): ?>

        <!-- Checklist de módulos -->
        <div class="grid sm:grid-cols-2 gap-4">

            <!-- Módulos com MATCH -->
            <div class="bg-white rounded-xl shadow-sm border border-green-100 overflow-hidden">
                <div class="bg-green-50 px-5 py-3 border-b border-green-100 flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="font-semibold text-green-800 text-sm">Módulos com Cobertura</h3>
                    <span class="ml-auto text-xs text-green-600 font-semibold">
                        <?= count($itensMatch['modulos_match'] ?? []) ?>
                    </span>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php if (empty($itensMatch['modulos_match'])): ?>
                    <p class="px-4 py-8 text-center text-sm text-gray-400">Nenhum módulo com match.</p>
                    <?php else: ?>
                    <?php foreach ($itensMatch['modulos_match'] as $m): ?>
                    <div class="px-4 py-3">
                        <div class="flex items-start gap-2">
                            <span class="text-green-500 text-base leading-none mt-0.5 flex-shrink-0">✅</span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($m['nome']) ?></p>
                                <?php if (!empty($m['categoria'])): ?>
                                <span class="text-xs text-gray-400"><?= htmlspecialchars($m['categoria']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($m['hits'])): ?>
                                <div class="flex flex-wrap gap-1 mt-1.5">
                                    <?php foreach (array_slice($m['hits'], 0, 3) as $hit): ?>
                                    <span class="px-1.5 py-0.5 bg-green-100 text-green-700 text-xs rounded">
                                        <?= htmlspecialchars($hit) ?>
                                    </span>
                                    <?php endforeach; ?>
                                    <?php if (count($m['hits']) > 3): ?>
                                    <span class="px-1.5 py-0.5 bg-gray-100 text-gray-500 text-xs rounded">
                                        +<?= count($m['hits']) - 3 ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="ml-auto text-xs text-green-600 font-bold whitespace-nowrap">AUTO</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Módulos PENDENTES -->
            <div class="bg-white rounded-xl shadow-sm border border-red-100 overflow-hidden">
                <div class="bg-red-50 px-5 py-3 border-b border-red-100 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="font-semibold text-red-700 text-sm">Módulos não Cobertos</h3>
                    <span class="ml-auto text-xs text-red-600 font-semibold">
                        <?= count($itensMatch['modulos_pendente'] ?? []) ?>
                    </span>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php if (empty($itensMatch['modulos_pendente'])): ?>
                    <p class="px-4 py-8 text-center text-sm text-gray-400">Todos os módulos foram cobertos!</p>
                    <?php else: ?>
                    <?php foreach ($itensMatch['modulos_pendente'] as $m): ?>
                    <div class="px-4 py-3 flex items-center gap-2">
                        <span class="text-red-400 text-base flex-shrink-0">❌</span>
                        <div class="min-w-0">
                            <p class="text-sm text-gray-700"><?= htmlspecialchars($m['nome']) ?></p>
                            <?php if (!empty($m['categoria'])): ?>
                            <span class="text-xs text-gray-400"><?= htmlspecialchars($m['categoria']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="ml-auto text-xs text-red-500 font-bold whitespace-nowrap">PENDENTE</span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Requisitos da IA não cobertos -->
        <?php if (!empty($itensMatch['termos_nao_atendidos'])): ?>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-amber-100">
            <h3 class="font-semibold text-gray-700 text-sm mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Requisitos do Edital sem Cobertura no Portfólio
                <span class="ml-auto text-xs text-amber-600"><?= count($itensMatch['termos_nao_atendidos']) ?> itens</span>
            </h3>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($itensMatch['termos_nao_atendidos'] as $termo): ?>
                <span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 text-xs rounded-full">
                    <?= htmlspecialchars($termo) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Requisitos extraídos pela IA -->
        <?php if ($requisitosIa): ?>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <h3 class="font-semibold text-gray-700 text-sm mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                Análise Completa da IA
            </h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <?php
                $secoes = [
                    ['chave' => 'modulos',        'label' => 'Módulos Exigidos',    'cor' => 'blue'],
                    ['chave' => 'funcionalidades','label' => 'Funcionalidades',       'cor' => 'purple'],
                    ['chave' => 'tecnologias',   'label' => 'Tecnologias',            'cor' => 'cyan'],
                    ['chave' => 'integracoes',   'label' => 'Integrações',            'cor' => 'teal'],
                ];
                $cores = ['blue'=>'bg-blue-50 text-blue-700 border-blue-200', 'purple'=>'bg-purple-50 text-purple-700 border-purple-200', 'cyan'=>'bg-cyan-50 text-cyan-700 border-cyan-200', 'teal'=>'bg-teal-50 text-teal-700 border-teal-200'];
                foreach ($secoes as $s):
                    $itens = $requisitosIa[$s['chave']] ?? [];
                    if (empty($itens)) continue;
                ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2"><?= $s['label'] ?></p>
                    <ul class="space-y-1">
                        <?php foreach ($itens as $item): ?>
                        <li class="px-2.5 py-1 <?= $cores[$s['cor']] ?> border text-xs rounded-lg">
                            <?= htmlspecialchars($item) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($statusAnalise === 'novo' || $statusAnalise === 'erro'): ?>
        <!-- Estado: ainda não analisado -->
        <div class="bg-white rounded-xl p-10 shadow-sm border border-gray-100 text-center">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
            <p class="text-gray-500 text-sm mb-1">Este edital ainda não foi analisado pela IA.</p>
            <p class="text-gray-400 text-xs mb-4">Clique no botão "Analisar com IA" para calcular o percentual de compatibilidade.</p>
            <?php if (!empty($edital['erro_analise'])): ?>
            <p class="text-red-500 text-xs mb-4 p-2 bg-red-50 rounded-lg">Erro anterior: <?= htmlspecialchars($edital['erro_analise']) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="notif" class="fixed bottom-4 right-4 z-50 hidden">
    <div class="px-4 py-3 bg-gray-800 text-white text-sm rounded-xl shadow-lg flex items-center gap-3">
        <div id="notifSpinner" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin hidden"></div>
        <span id="notifMsg"></span>
    </div>
</div>

<script>
// ─── Análise via IA (botão manual) ───────────────────────────────────────────
function analisarAgora(id) {
    const btn = document.getElementById('btnAnalisar');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Analisando...';
    }
    mostrarNotif('Enviando Termo de Referência para a IA...', true);

    const fd = new FormData();
    fd.append('csrf_token', window.CSRF_TOKEN || '');
    fetch('?action=analisar_edital&id=' + id, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.sucesso) {
                mostrarNotif('✓ Compatibilidade: ' + d.porcentagem + '%');
                setTimeout(() => location.reload(), 1500);
            } else {
                mostrarNotif('Erro: ' + d.mensagem, false);
                if (btn) { btn.disabled = false; btn.textContent = 'Tentar novamente'; }
            }
        })
        .catch(() => {
            mostrarNotif('Erro de conexão.');
            if (btn) btn.disabled = false;
        });
}

// ─── Upload de arquivo TR ─────────────────────────────────────────────────────
let arquivoSelecionado = null;

function selecionarArquivo(input) {
    if (!input.files.length) return;
    arquivoSelecionado = input.files[0];
    document.getElementById('nomeArquivo').textContent = '📄 ' + arquivoSelecionado.name + ' (' + (arquivoSelecionado.size / 1024).toFixed(0) + ' KB)';
    document.getElementById('nomeArquivo').classList.remove('hidden');
    document.getElementById('btnUpload').classList.remove('hidden');
}

function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('border-blue-400', 'bg-blue-50');
    const file = event.dataTransfer.files[0];
    if (!file) return;
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['pdf','doc','docx','txt'].includes(ext)) {
        mostrarNotif('Formato não suportado. Use PDF, DOC, DOCX ou TXT.', false);
        return;
    }
    arquivoSelecionado = file;
    document.getElementById('nomeArquivo').textContent = '📄 ' + file.name;
    document.getElementById('nomeArquivo').classList.remove('hidden');
    document.getElementById('btnUpload').classList.remove('hidden');
}

async function enviarUpload(editalId) {
    if (!arquivoSelecionado) {
        mostrarNotif('Selecione um arquivo primeiro.', false);
        return;
    }

    const btnUp  = document.getElementById('btnUpload');
    const prog   = document.getElementById('uploadProgress');
    const status = document.getElementById('uploadStatus');

    btnUp.classList.add('hidden');
    prog.classList.remove('hidden');
    status.textContent = 'Enviando arquivo...';
    mostrarNotif('Enviando ' + arquivoSelecionado.name + '...', true);

    const formData = new FormData();
    formData.append('arquivo_tr', arquivoSelecionado);

    try {
        status.textContent = 'Extraindo texto e enviando para IA...';
        const resp = await fetch('?action=upload_tr&id=' + editalId, {
            method: 'POST',
            body: formData,
        });
        const data = await resp.json();
        prog.classList.add('hidden');

        if (data.sucesso) {
            mostrarNotif('✓ Upload concluído! Compatibilidade: ' + data.porcentagem + '%');
            setTimeout(() => location.reload(), 1800);
        } else {
            mostrarNotif('Erro: ' + data.mensagem, false);
            btnUp.classList.remove('hidden');
        }
    } catch (e) {
        prog.classList.add('hidden');
        mostrarNotif('Falha no envio. Verifique sua conexão.', false);
        btnUp.classList.remove('hidden');
    }
}

// ─── Toast de notificação ─────────────────────────────────────────────────────
function mostrarNotif(msg, loading = false) {
    const el = document.getElementById('notif');
    const sp = document.getElementById('notifSpinner');
    document.getElementById('notifMsg').textContent = msg;
    sp.classList.toggle('hidden', !loading);
    el.classList.remove('hidden');
    if (!loading) setTimeout(() => el.classList.add('hidden'), 5000);
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
