<?php
/**
 * LicitAI - Formulário de Edital (Criar / Editar Manual)
 *
 * Permite o cadastro manual de editais de portais que não o PNCP,
 * ou a correção de dados capturados automaticamente.
 */
$editando  = !empty($edital);
$pageTitle = ($editando ? 'Editar Edital' : 'Cadastrar Edital') . ' — ' . APP_NAME;
include __DIR__ . '/../layout/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-xs text-gray-500 mb-5">
    <a href="?page=dashboard" class="hover:text-blue-600 transition-colors">Dashboard</a>
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-700"><?= $editando ? 'Editar Edital' : 'Novo Edital' ?></span>
</nav>

<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h2 class="font-semibold text-gray-700 text-sm">
                <?= $editando ? 'Editar Edital #' . $edital['id'] : 'Cadastrar Edital Manualmente' ?>
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Use este formulário para editais de outros portais ou para corrigir dados capturados automaticamente.
            </p>
        </div>

        <form method="POST" action="?action=salvar_edital" class="px-6 py-5 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= AuthController::csrfToken() ?>">
            <?php if ($editando): ?>
            <input type="hidden" name="id" value="<?= $edital['id'] ?>">
            <?php endif; ?>

            <!-- Órgão + CNPJ -->
            <div class="grid sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="orgao">
                        Órgão / Entidade <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="orgao" name="orgao" required
                           value="<?= htmlspecialchars($edital['orgao'] ?? '') ?>"
                           placeholder="Ex: Prefeitura Municipal de São Paulo"
                           class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="cnpj_orgao">
                        CNPJ do Órgão
                    </label>
                    <div class="relative">
                        <input type="text" id="cnpj_orgao" name="cnpj_orgao"
                               value="<?= htmlspecialchars($edital['cnpj_orgao'] ?? '') ?>"
                               placeholder="00.000.000/0000-00" maxlength="18"
                               class="w-full px-3 py-2.5 pr-10 text-sm border border-gray-200 rounded-lg
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                        <button type="button" onclick="consultarCNPJ()" title="Consultar na Receita Federal"
                                class="absolute right-2 top-2 text-gray-400 hover:text-blue-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </button>
                    </div>
                    <p id="cnpjInfo" class="text-xs text-green-600 mt-1 hidden"></p>
                </div>
            </div>

            <!-- Objeto -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="objeto">
                    Objeto da Licitação <span class="text-red-500">*</span>
                </label>
                <textarea id="objeto" name="objeto" rows="3" required
                          placeholder="Descrição completa do objeto licitado..."
                          class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors resize-y"><?= htmlspecialchars($edital['objeto'] ?? '') ?></textarea>
            </div>

            <!-- Modalidade + Valor -->
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="modalidade">
                        Modalidade
                    </label>
                    <select id="modalidade" name="modalidade"
                            class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                        <option value="">Selecione...</option>
                        <?php
                        $modalidades = ['Pregão Eletrônico','Pregão Presencial','Concorrência Eletrônica',
                                        'Concorrência','Dispensa de Licitação','Inexigibilidade',
                                        'Convite','Tomada de Preços','RDC Eletrônico'];
                        $modalidadeNorm = preg_replace('/\s*-\s*/', ' ', trim($edital['modalidade'] ?? ''));
                        foreach ($modalidades as $m):
                            $sel = $modalidadeNorm === $m ? 'selected' : '';
                        ?>
                        <option value="<?= $m ?>" <?= $sel ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="valor_estimado">
                        Valor Estimado (R$)
                    </label>
                    <input type="number" id="valor_estimado" name="valor_estimado"
                           step="0.01" min="0"
                           value="<?= htmlspecialchars($edital['valor_estimado'] ?? '') ?>"
                           placeholder="0,00"
                           class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                </div>
            </div>

            <!-- Datas -->
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="data_publicacao">
                        Data de Publicação
                    </label>
                    <input type="date" id="data_publicacao" name="data_publicacao"
                           value="<?= htmlspecialchars($edital['data_publicacao'] ?? '') ?>"
                           class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="data_encerramento">
                        Data de Encerramento
                    </label>
                    <input type="date" id="data_encerramento" name="data_encerramento"
                           value="<?= htmlspecialchars($edital['data_encerramento'] ?? '') ?>"
                           class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                </div>
            </div>

            <!-- Link + ID PNCP -->
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="link_edital">
                        Link do Edital
                    </label>
                    <input type="url" id="link_edital" name="link_edital"
                           value="<?= htmlspecialchars($edital['link_edital'] ?? '') ?>"
                           placeholder="https://..."
                           class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="pncp_id">
                        ID PNCP (opcional)
                    </label>
                    <input type="text" id="pncp_id" name="pncp_id"
                           value="<?= htmlspecialchars($edital['pncp_id'] ?? '') ?>"
                           placeholder="Ex: 00000000000000-2-000001/2025"
                           class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors font-mono text-xs">
                </div>
            </div>

            <!-- Upload do TR -->
            <div class="p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span class="text-sm font-semibold text-indigo-700">Termo de Referência (Análise IA)</span>
                </div>
                <p class="text-xs text-indigo-600 mb-3">
                    Opcional: envie o TR agora ou faça upload depois na tela de detalhes.
                    O sistema extrairá os requisitos e calculará a compatibilidade automaticamente.
                </p>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1" for="texto_tr_manual">
                        Cole o texto do TR aqui (ou faça upload do arquivo depois):
                    </label>
                    <textarea id="texto_tr_manual" name="texto_tr"
                              rows="4" placeholder="Cole aqui o texto do Termo de Referência..."
                              class="w-full px-3 py-2.5 text-xs border border-indigo-200 rounded-lg
                                     focus:outline-none focus:ring-2 focus:ring-indigo-400 transition-colors
                                     bg-white resize-y font-mono"><?= htmlspecialchars($edital['texto_tr'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold
                               rounded-lg transition-colors shadow-sm">
                    <?= $editando ? 'Salvar Alterações' : 'Cadastrar Edital' ?>
                </button>
                <?php if ($editando): ?>
                <a href="?page=detalhes&id=<?= $edital['id'] ?>"
                   class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    Cancelar
                </a>
                <?php else: ?>
                <a href="?page=dashboard"
                   class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    Cancelar
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Consulta CNPJ via ReceitaWS e preenche o campo Órgão automaticamente.
 * Integração com API externa pública para validação de dados.
 */
async function consultarCNPJ() {
    const cnpj = document.getElementById('cnpj_orgao').value.replace(/\D/g, '');
    const info = document.getElementById('cnpjInfo');

    if (cnpj.length !== 14) {
        alert('Digite um CNPJ válido com 14 dígitos.');
        return;
    }

    info.textContent = 'Consultando Receita Federal...';
    info.className = 'text-xs text-blue-600 mt-1';
    info.classList.remove('hidden');

    try {
        const resp = await fetch(`?action=consultar_cnpj&cnpj=${cnpj}`);
        const data = await resp.json();

        if (data.sucesso) {
            document.getElementById('orgao').value = data.razao_social;
            info.textContent = `✓ ${data.razao_social} — ${data.municipio}/${data.uf}`;
            info.className = 'text-xs text-green-600 mt-1';
        } else {
            info.textContent = data.mensagem || 'CNPJ não encontrado.';
            info.className = 'text-xs text-red-500 mt-1';
        }
    } catch {
        info.textContent = 'Erro ao consultar. Tente novamente.';
        info.className = 'text-xs text-red-500 mt-1';
    }
}

// Máscara CNPJ
document.getElementById('cnpj_orgao').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/, '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
    v = v.replace(/(\d{4})(\d)/, '$1-$2');
    this.value = v;
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
