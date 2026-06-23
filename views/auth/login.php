<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e40af">
    <title>Login — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="manifest" href="public/manifest.json">
    <style>
        @keyframes fadeUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:none; } }
        .fade-up { animation: fadeUp 0.5s ease forwards; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-900 via-blue-800 to-indigo-900 flex items-center justify-center p-4">
<script>tailwind.config={theme:{extend:{colors:{brand:{900:'#1e3a8a',800:'#1e40af',700:'#1d4ed8'}}}}}</script>

<div class="w-full max-w-md fade-up">

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

        <!-- Header do card -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-700 px-8 py-8 text-center">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-white/20 rounded-2xl mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white">LicitAI</h1>
            <p class="text-blue-200 text-sm mt-1">Inteligência em Licitações de TI</p>
        </div>

        <!-- Form -->
        <div class="px-8 py-8">
            <?php if (!empty($_SESSION['login_erro'])): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
                <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span class="text-red-700 text-sm"><?= htmlspecialchars($_SESSION['login_erro']) ?></span>
            </div>
            <?php unset($_SESSION['login_erro']); endif; ?>

            <?php if (($_GET['msg'] ?? '') === 'expirou'): ?>
            <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-700 text-sm">
                Sua sessão expirou. Faça login novamente.
            </div>
            <?php endif; ?>

            <form method="POST" action="?action=login" novalidate>
                <input type="hidden" name="csrf_token" value="<?= AuthController::csrfToken() ?>">

                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5" for="email">
                            E-mail
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </div>
                            <input type="email" id="email" name="email" required autocomplete="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                          transition-colors"
                                   placeholder="seu@email.com">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5" for="senha">
                            Senha
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input type="password" id="senha" name="senha" required autocomplete="current-password"
                                   class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg text-sm
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                          transition-colors"
                                   placeholder="••••••••">
                            <button type="button" onclick="toggleSenha()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg id="olhoAberto" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg id="olhoFechado" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Lembrar-me -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer select-none group">
                            <div class="relative">
                                <input type="checkbox" id="lembrarMe" name="lembrar_me" value="1"
                                       class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 rounded-full peer-checked:bg-blue-600
                                            transition-colors peer-focus:ring-2 peer-focus:ring-blue-400
                                            peer-focus:ring-offset-1"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow
                                            transition-transform peer-checked:translate-x-4"></div>
                            </div>
                            <span class="text-sm text-gray-600 group-hover:text-gray-800 transition-colors">
                                Lembrar login e senha
                            </span>
                        </label>
                        <span id="lembrancaStatus" class="text-xs text-gray-400 hidden">Salvo</span>
                    </div>

                    <button type="submit" id="btnEntrar"
                            class="w-full py-2.5 px-4 bg-blue-700 hover:bg-blue-800 text-white font-semibold
                                   rounded-lg text-sm transition-colors shadow-md hover:shadow-lg
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Entrar no Sistema
                    </button>
                </div>
            </form>
        </div>

        <div class="px-8 pb-6 text-center text-xs text-gray-400">
            LicitAI <?= APP_VERSION ?> · Acesso seguro com criptografia bcrypt
        </div>
    </div>
</div>

<script>
function toggleSenha() {
    const campo = document.getElementById('senha');
    campo.type = campo.type === 'password' ? 'text' : 'password';
    document.getElementById('olhoAberto').classList.toggle('hidden');
    document.getElementById('olhoFechado').classList.toggle('hidden');
}

// ── Lembrar-me ──────────────────────────────────────────────────────────────
(function () {
    const KEY_EMAIL  = 'licita_email';
    const KEY_SENHA  = 'licita_senha';
    const KEY_ATIVO  = 'licita_lembrar';

    const elEmail   = document.getElementById('email');
    const elSenha   = document.getElementById('senha');
    const elCheck   = document.getElementById('lembrarMe');
    const elStatus  = document.getElementById('lembrancaStatus');
    const elForm    = elEmail.closest('form');

    // Carrega credenciais salvas na abertura da página
    if (localStorage.getItem(KEY_ATIVO) === '1') {
        const email = localStorage.getItem(KEY_EMAIL) || '';
        const senha = localStorage.getItem(KEY_SENHA) || '';
        if (email) {
            elEmail.value = email;
            elSenha.value = senha;
            elCheck.checked = true;
            elStatus.textContent = 'Salvo';
            elStatus.classList.remove('hidden');
        }
    }

    // Botão de limpar lembrança ao desmarcar
    elCheck.addEventListener('change', function () {
        if (!this.checked) {
            localStorage.removeItem(KEY_EMAIL);
            localStorage.removeItem(KEY_SENHA);
            localStorage.removeItem(KEY_ATIVO);
            elStatus.classList.add('hidden');
        }
    });

    // Salva (ou remove) ao enviar o formulário
    elForm.addEventListener('submit', function () {
        if (elCheck.checked) {
            localStorage.setItem(KEY_EMAIL, elEmail.value.trim());
            localStorage.setItem(KEY_SENHA, elSenha.value);
            localStorage.setItem(KEY_ATIVO, '1');
        } else {
            localStorage.removeItem(KEY_EMAIL);
            localStorage.removeItem(KEY_SENHA);
            localStorage.removeItem(KEY_ATIVO);
        }
    });
})();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('public/sw.js').catch(() => {});
}
</script>
</body>
</html>
