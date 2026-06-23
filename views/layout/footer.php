        </main>
    </div><!-- /conteúdo principal -->
</div><!-- /flex wrapper -->

<script>
// Token CSRF global para todas as requisições AJAX — gerado server-side uma vez por sessão
window.CSRF_TOKEN = '<?= AuthController::csrfToken() ?>';

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// PWA: registra service worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('public/sw.js').catch(() => {});
}

// Auto-dismiss flash messages (somente elementos com .flash-msg)
setTimeout(() => {
    document.querySelectorAll('.flash-msg').forEach(el => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 4000);

// Painel de notificações
function toggleNotif() {
    const panel = document.getElementById('notifPanel');
    if (panel) panel.classList.toggle('hidden');
}
// Fecha o painel ao clicar fora
document.addEventListener('click', (e) => {
    const wrapper = document.getElementById('notifWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        document.getElementById('notifPanel')?.classList.add('hidden');
    }
});
</script>
</body>
</html>
