/*
 * VoxPilot POS admin: light/dark toggle in the header and the VoxPilot logo. The theme itself is
 * applied before first paint by the inline script the VoxPilot extension adds to <head>; this file
 * only adds the toggle and keeps the choice (localStorage "vp-theme": "light" | "dark").
 */
(function () {
    var KEY = 'vp-theme';
    var root = document.documentElement;

    function stored() {
        try { return localStorage.getItem(KEY); } catch (e) { return null; }
    }

    function apply(theme) {
        root.setAttribute('data-bs-theme', theme);
        var logo = document.querySelector('.navbar-top .logo-svg');
        if (logo) logo.src = theme === 'dark' ? '/voxpilot/logo-on-dark.svg' : '/voxpilot/logo.svg';
    }

    function toggle() {
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem(KEY, next); } catch (e) { /* private mode: session only */ }
        apply(next);
    }

    function addToggle() {
        var menu = document.getElementById('menu-mainmenu');
        if (!menu || menu.querySelector('.vp-theme-toggle')) return;
        var item = document.createElement('li');
        item.className = 'nav-item vp-theme-toggle';
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'nav-link border-0 bg-transparent';
        button.setAttribute('aria-label', 'Switch light and dark mode');
        button.innerHTML =
            '<svg class="vp-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>' +
            '<svg class="vp-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
        button.addEventListener('click', toggle);
        item.appendChild(button);
        menu.insertBefore(item, menu.firstChild);
    }

    function init() {
        apply(root.getAttribute('data-bs-theme') || 'light');
        addToggle();
    }

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
            if (!stored()) apply(e.matches ? 'dark' : 'light');
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
