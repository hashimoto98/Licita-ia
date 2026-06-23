<?php
$editando = !empty($modulo);
$pageTitle = ($editando ? 'Editar Módulo' : 'Novo Módulo') . ' — ' . APP_NAME;
include __DIR__ . '/../layout/header.php';

$kw = $editando ? json_decode($modulo['palavras_chave'] ?? '[]', true) : [];
$kwStr = $editando ? implode(', ', $kw) : '';
?>

<nav class="flex items-center gap-2 text-xs text-gray-500 mb-5">
    <a href="?page=modulos" class="hover:text-blue-600 transition-colors">Catálogo de Módulos</a>
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-700"><?= $editando ? 'Editar' : 'Novo' ?></span>
</nav>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h2 class="font-semibold text-gray-700 text-sm">
                <?= $editando ? 'Editar Módulo: ' . htmlspecialchars($modulo['nome']) : 'Cadastrar Novo Módulo' ?>
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">As palavras-chave são usadas pelo algoritmo de match para identificar compatibilidade com editais.</p>
        </div>

        <form method="POST" action="?action=salvar_modulo" class="px-6 py-5 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= AuthController::csrfToken() ?>">
            <?php if ($editando): ?>
            <input type="hidden" name="id" value="<?= $modulo['id'] ?>">
            <?php endif; ?>

            <div class="grid sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="nome">
                        Nome do Módulo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nome" name="nome" required
                           value="<?= htmlspecialchars($modulo['nome'] ?? '') ?>"
                           placeholder="Ex: Sistema de Folha de Pagamento"
                           class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="categoria">
                        Categoria
                    </label>
                    <input type="text" id="categoria" name="categoria" list="listaCategorias"
                           value="<?= htmlspecialchars($modulo['categoria'] ?? '') ?>"
                           placeholder="Ex: Recursos Humanos"
                           class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <datalist id="listaCategorias">
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="versao">
                        Versão
                    </label>
                    <input type="text" id="versao" name="versao"
                           value="<?= htmlspecialchars($modulo['versao'] ?? '') ?>"
                           placeholder="Ex: 3.2.1"
                           class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="descricao">
                    Descrição
                </label>
                <textarea id="descricao" name="descricao" rows="2"
                          placeholder="Breve descrição das funcionalidades do módulo..."
                          class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"><?= htmlspecialchars($modulo['descricao'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="palavras_chave">
                    Palavras-chave para Match <span class="text-red-500">*</span>
                </label>
                <textarea id="palavras_chave" name="palavras_chave" rows="4" required
                          placeholder="folha de pagamento, esocial, gfip, inss, fgts, holerite, contracheque"
                          class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-y font-mono"><?= htmlspecialchars($kwStr) ?></textarea>
                <p class="text-xs text-gray-400 mt-1">Separe por vírgula. Use termos que aparecem em editais — sinônimos, siglas, nomes de sistemas.</p>

                <!-- Preview das tags -->
                <div id="kwPreview" class="mt-2 flex flex-wrap gap-1 min-h-6"></div>
            </div>

            <?php if ($editando): ?>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="ativo" name="ativo" value="1"
                       <?= ($modulo['ativo'] ?? 1) ? 'checked' : '' ?>
                       class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="ativo" class="text-sm text-gray-700">Módulo ativo (incluído no algoritmo de match)</label>
            </div>
            <?php endif; ?>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold
                               rounded-lg transition-colors shadow-sm">
                    <?= $editando ? 'Salvar Alterações' : 'Cadastrar Módulo' ?>
                </button>
                <a href="?page=modulos"
                   class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium
                          rounded-lg transition-colors">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Preview de tags das palavras-chave
const kwInput = document.getElementById('palavras_chave');
const kwPreview = document.getElementById('kwPreview');

function atualizarPreview() {
    const palavras = kwInput.value.split(',').map(p => p.trim()).filter(Boolean);
    kwPreview.innerHTML = palavras.map(p =>
        `<span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded-full border border-blue-100">${p}</span>`
    ).join('');
}

kwInput.addEventListener('input', atualizarPreview);
atualizarPreview(); // inicial
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
