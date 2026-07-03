<footer class="text-center py-3 mt-2 small">
    <a href="https://github.com/guibranco/projects-monitor" target="_blank" rel="noopener noreferrer" class="footer-link">
        <i class="bi bi-github me-1"></i>guibranco/projects-monitor
    </a>
</footer>

<script>
    function toggleTheme() {
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        localStorage.setItem('pm-theme', next);
        syncThemeIcons(next);
    }

    function syncThemeIcons(theme) {
        document.querySelectorAll('.theme-toggle-icon').forEach(function (el) {
            el.className = 'fas theme-toggle-icon ' + (theme === 'dark' ? 'fa-sun' : 'fa-moon');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncThemeIcons(document.documentElement.getAttribute('data-bs-theme') || 'light');
    });
</script>
