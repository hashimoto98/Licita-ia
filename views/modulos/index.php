<?php
$pageTitle = 'Catálogo de Módulos — ' . APP_NAME;
include __DIR__ . '/../layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-base font-semibold text-gray-800">Catálogo de Módulos da Empresa</h2>
        <p class="text-xs text-gray-500 mt-0.5">
            <?= count($modulos) ?> módulos cadastrados · palavras-chave usadas no algoritmo de match
        </p>
    </div>
    <a href="?page=modulos&action=novo"
       class="flex items-center gap-2 px-4 py-2 bg-blue-700 text-white text-sm font-medium rounded-lg hover:bg-blue-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Novo Módulo
    </a>
</div>

<?php if (!empty($_GET['ok'])): ?>
<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2 fade-in flash-msg">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
    </svg>
    Operação realizada com sucesso.
</div>
<?php endif; ?>

<?php if (empty($modulos)): ?>
<div class="bg-white rounded-xl p-16 text-center shadow-sm border border-gray-100">
    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
    </svg>
    <p class="text-gray-500 text-sm">Nenhum módulo cadastrado.</p>
    <a href="?page=modulos&action=novo" class="inline-block mt-3 text-blue-600 text-sm hover:underline">Cadastrar primeiro módulo →</a>
</div>
<?php else: ?>

<!-- Agrupado por categoria -->
<?php
$porCategoria = [];
foreach ($modulos as $m) {
    $cat = $m['categoria'] ?: 'Sem Categoria';
    $porCategoria[$cat][] = $m;
}
ksort($porCategoria);
?>

<div class="space-y-5">
    <?php foreach ($porCategoria as $categoria => $itens): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            <h3 class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($categoria) ?></h3>
            <span class="ml-auto text-xs text-gray-400"><?= count($itens) ?> módulos</span>
        </div>
        <div class="divide-y divide-gray-50">
            <?php foreach ($itens as $m):
                $kw = json_decode($m['palavras_chave'] ?? '[]', true) ?: [];
            ?>
            <div class="px-5 py-4 flex items-start gap-4 <?= !$m['ativo'] ? 'opacity-50' : '' ?>">
                <!-- Status dot -->
                <div class="mt-1 flex-shrink-0">
                    <div class="w-2.5 h-2.5 rounded-full <?= $m['ativo'] ? 'bg-green-400' : 'bg-gray-300' ?>"></div>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($m['nome']) ?></span>
                        <?php if ($m['versao']): ?>
                        <span class="px-1.5 py-0.5 bg-gray-100 text-gray-500 text-xs rounded"><?= htmlspecialchars($m['versao']) ?></span>
                        <?php endif; ?>
                        <?php if (!$m['ativo']): ?>
                        <span class="px-1.5 py-0.5 bg-gray-100 text-gray-400 text-xs rounded">Inativo</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($m['descricao']): ?>
                    <p class="text-xs text-gray-500 mb-2"><?= htmlspecialchars($m['descricao']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($kw)): ?>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach (array_slice($kw, 0, 8) as $k): ?>
                        <span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded-full border border-blue-100">
                            <?= htmlspecialchars($k) ?>
                        </span>
                        <?php endforeach; ?>
                        <?php if (count($kw) > 8): ?>
                        <span class="px-2 py-0.5 bg-gray-50 text-gray-400 text-xs rounded-full border border-gray-100">
                            +<?= count($kw) - 8 ?> mais
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Ações -->
                <div class="flex items-center gap-1 flex-shrink-0">
                    <a href="?page=modulos&action=editar&id=<?= $m['id'] ?>"
                       class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                       title="Editar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                    <form method="POST" action="?action=excluir_modulo"
                          onsubmit="return confirm('Excluir o módulo \'<?= htmlspecialchars(addslashes($m['nome'])) ?>\'? Esta ação não pode ser desfeita.')">
                        <input type="hidden" name="csrf_token" value="<?= AuthController::csrfToken() ?>">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button type="submit" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Excluir">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
