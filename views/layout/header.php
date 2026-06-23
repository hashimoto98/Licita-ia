<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e40af">
    <meta name="description" content="LicitAI — Sistema Inteligente de Análise de Licitações de TI">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>

    <!-- PWA -->
    <link rel="manifest" href="public/manifest.json">
    <link rel="apple-touch-icon" href="public/icons/icon-192.png">

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { 50:'#eff6ff', 100:'#dbeafe', 500:'#3b82f6', 600:'#2563eb', 700:'#1d4ed8', 800:'#1e40af', 900:'#1e3a8a' }
                    }
                }
            }
        }
    </script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .sidebar-transition { transition: transform 0.3s ease; }
        .match-bar { transition: width 0.8s ease-out; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
        .fade-in { animation: fadeIn 0.4s ease forwards; }
        .card-hover { transition: box-shadow 0.2s, transform 0.2s; }
        .card-hover:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,.12); transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<!-- ─── Sidebar ─────────────────────────────────────────────────── -->
<div class="flex h-screen overflow-hidden">
    <aside id="sidebar" class="sidebar-transition w-64 bg-brand-800 text-white flex-shrink-0 flex flex-col shadow-xl z-30
                               fixed md:static h-full -translate-x-full md:translate-x-0" aria-label="Menu principal">

        <!-- Logo -->
        <div class="flex items-center gap-3 px-6 py-5 border-b border-brand-700">
            <div class="w-9 h-9 bg-brand-500 rounded-lg flex items-center justify-center shadow-md">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <span class="text-lg font-bold tracking-tight">LicitAI</span>
                <span class="block text-xs text-brand-300 -mt-0.5">v<?= APP_VERSION ?></span>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <?php
            $currentPage = $_GET['page'] ?? 'dashboard';
            $navItems = [
                ['page' => 'dashboard',  'label' => 'Dashboard',          'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                ['page' => 'relatorios', 'label' => 'Relatórios',          'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                ['page' => 'modulos',    'label' => 'Catálogo de Módulos', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
            ];
            // Link rápido para cadastro manual de edital
            $navExtras = [
                ['href' => '?page=editais&action=novo', 'label' => '+ Novo Edital', 'icon' => 'M12 4v16m8-8H4'],
            ];
            if (($_SESSION['usuario_perfil'] ?? '') === 'admin') {
                $navItems[] = ['page' => 'admin', 'label' => 'Administração', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'];
            }
            foreach ($navItems as $item):
                $isActive = $currentPage === $item['page'];
            ?>
            <a href="?page=<?= $item['page'] ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      <?= $isActive ? 'bg-brand-600 text-white shadow-sm' : 'text-brand-200 hover:bg-brand-700 hover:text-white' ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
                </svg>
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach; ?>

            <!-- Separador + ações rápidas -->
            <div class="border-t border-brand-700 mt-2 pt-2 space-y-1">
                <a href="?page=editais&action=novo"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-brand-300 hover:bg-brand-700 hover:text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Cadastrar Edital
                </a>
            </div>
        </nav>

        <!-- User info -->
        <div class="border-t border-brand-700 p-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center text-sm font-bold flex-shrink-0">
                    <?= strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? '') ?></p>
                    <p class="text-xs text-brand-300 truncate"><?= htmlspecialchars($_SESSION['usuario_perfil'] ?? '') ?></p>
                </div>
                <a href="?action=logout" title="Sair"
                   class="text-brand-300 hover:text-white transition-colors"
                   onclick="return confirm('Deseja sair do sistema?')">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Overlay mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- ─── Conteúdo Principal ─────────────────────────────── -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- Topbar -->
        <header class="bg-white border-b border-gray-200 px-4 md:px-6 py-4 flex items-center gap-4 shadow-sm flex-shrink-0">
            <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-700" aria-label="Menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <h1 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></h1>
            <div class="ml-auto flex items-center gap-3">
                <span class="hidden sm:inline-block text-xs text-gray-400"><?= date('d/m/Y H:i') ?></span>

                <?php
                // Badge de notificações: editais novos/pendentes de análise
                try {
                    $db = Database::getInstance()->getConnection();
                    $nPendentes  = (int) $db->query("SELECT COUNT(*) FROM editais WHERE status='novo'")->fetchColumn();
                    $nAltaMatch  = (int) $db->query("SELECT COUNT(*) FROM editais WHERE status='analisado' AND porcentagem_match >= " . MATCH_HIGH . " AND date(criado_em) = date('now')")->fetchColumn();
                } catch (\Throwable $e) {
                    $nPendentes = 0; $nAltaMatch = 0;
                }
                ?>
                <!-- Sino de notificações -->
                <div class="relative" id="notifWrapper">
                    <button onclick="toggleNotif()" id="notifBtn"
                            class="relative p-2 text-gray-500 hover:text-blue-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title="Notificações"
                            aria-label="Notificações">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <?php if ($nPendentes > 0): ?>
                        <span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-blue-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                            <?= $nPendentes > 9 ? '9+' : $nPendentes ?>
                        </span>
                        <?php endif; ?>
                    </button>

                    <!-- Painel de notificações -->
                    <div id="notifPanel"
                         class="hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-lg border border-gray-200 z-50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-800">Notificações</span>
                            <?php if ($nPendentes > 0 || $nAltaMatch > 0): ?>
                            <span class="text-xs text-blue-600 font-medium"><?= $nPendentes + $nAltaMatch ?> novas</span>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">Sem novidades</span>
                            <?php endif; ?>
                        </div>
                        <div class="divide-y divide-gray-50 max-h-64 overflow-y-auto">
                            <?php if ($nPendentes > 0): ?>
                            <a href="?page=dashboard&status=novo"
                               class="flex items-start gap-3 px-4 py-3 hover:bg-blue-50 transition-colors">
                                <div class="mt-0.5 w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800"><?= $nPendentes ?> edital<?= $nPendentes > 1 ? 'is' : '' ?> pendente<?= $nPendentes > 1 ? 's' : '' ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5">Aguardando análise com IA</p>
                                </div>
                            </a>
                            <?php endif; ?>
                            <?php if ($nAltaMatch > 0): ?>
                            <a href="?page=dashboard&match_min=<?= MATCH_HIGH ?>"
                               class="flex items-start gap-3 px-4 py-3 hover:bg-green-50 transition-colors">
                                <div class="mt-0.5 w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800"><?= $nAltaMatch ?> oportunidade<?= $nAltaMatch > 1 ? 's' : '' ?> de alta compatibilidade</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Capturadas hoje · ≥<?= MATCH_HIGH ?>% de match</p>
                                </div>
                            </a>
                            <?php endif; ?>
                            <?php if ($nPendentes === 0 && $nAltaMatch === 0): ?>
                            <div class="px-4 py-6 text-center">
                                <p class="text-sm text-gray-400">Tudo em dia por aqui.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <a href="?page=dashboard" class="block px-4 py-2.5 text-center text-xs text-blue-600 hover:bg-gray-50 border-t border-gray-100 font-medium">
                            Ver todos os editais →
                        </a>
                    </div>
                </div>

                <?php if (!empty($_GET['ok'])): ?>
                <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full fade-in flash-msg">
                    ✓ Operação realizada
                </span>
                <?php endif; ?>
            </div>
        </header>

        <!-- Conteúdo da página -->
        <main class="flex-1 overflow-y-auto p-4 md:p-6">
