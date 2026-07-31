{{-- Shared <head> bits: favicon + theme (light/dark/system) + tiny helpers. --}}
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}">
<script>
    // ── Theme: 'light' | 'dark' | 'system' (default) ──────────────────
    function egThemeMode() {
        try { return localStorage.getItem('eg-theme') || 'system'; } catch (e) { return 'system'; }
    }
    function egPrefersDark() {
        return window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches;
    }
    function egResolvedDark(mode) {
        mode = mode || egThemeMode();
        return mode === 'dark' || (mode === 'system' && egPrefersDark());
    }
    function egApplyTheme() {
        document.documentElement.classList.toggle('dark', egResolvedDark());
        egSyncThemeUI();
    }
    function egSetTheme(mode) {
        try { localStorage.setItem('eg-theme', mode); } catch (e) {}
        egApplyTheme();
        // close the open dropdown after choosing
        document.querySelectorAll('details.eg-dd[open]').forEach(function (d) { d.removeAttribute('open'); });
    }
    function egSyncThemeUI() {
        var mode = egThemeMode();
        document.querySelectorAll('[data-eg-theme-opt]').forEach(function (el) {
            el.setAttribute('aria-current', el.getAttribute('data-eg-theme-opt') === mode ? 'true' : 'false');
        });
    }

    // Apply BEFORE paint (no flash). UI sync happens again once the DOM is ready.
    document.documentElement.classList.toggle('dark', egResolvedDark());

    // Follow the OS when in 'system' mode.
    if (window.matchMedia) {
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (egThemeMode() === 'system') egApplyTheme();
        });
    }

    // Keep the switcher's active state correct across loads + Livewire navigations.
    document.addEventListener('DOMContentLoaded', egSyncThemeUI);
    document.addEventListener('livewire:navigated', egApplyTheme);

    // Close any open <details.eg-dd> when clicking outside it.
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.eg-dd[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
</script>
