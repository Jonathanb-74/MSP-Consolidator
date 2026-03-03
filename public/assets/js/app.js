/**
 * MSP Consolidator — Application JS
 */
(function () {
    'use strict';

    // ── Dark Mode ───────────────────────────────────────────────
    const DARK_MODE_KEY = 'msp_dark_mode';
    const htmlEl        = document.documentElement;
    const toggle        = document.getElementById('darkModeToggle');

    function applyTheme(dark) {
        htmlEl.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        if (toggle) toggle.checked = dark;
    }

    // Charger la préférence depuis localStorage (défaut : dark)
    const savedPref = localStorage.getItem(DARK_MODE_KEY);
    applyTheme(savedPref === null ? true : savedPref === 'true');

    // Toggle
    toggle?.addEventListener('change', function () {
        localStorage.setItem(DARK_MODE_KEY, this.checked ? 'true' : 'false');
        applyTheme(this.checked);
    });

    // ── Tooltips Bootstrap ──────────────────────────────────────
    document.querySelectorAll('[title]').forEach(el => {
        if (el.title) {
            new bootstrap.Tooltip(el, { trigger: 'hover', placement: 'top' });
        }
    });

    // ── Auto-dismiss alerts ─────────────────────────────────────
    document.querySelectorAll('.alert.alert-success').forEach(el => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert?.close();
        }, 5000);
    });

    // ── Confirm delete/unlink ───────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || 'Confirmer cette action ?')) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

})();
