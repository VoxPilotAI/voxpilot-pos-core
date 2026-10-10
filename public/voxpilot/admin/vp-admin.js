/*
 * VoxPilot POS admin: light/dark toggle in the header and the VoxPilot logo. The theme itself is
 * applied before first paint by the inline script the VoxPilot extension adds to <head>; this file
 * only adds the toggle and keeps the choice (localStorage "vp-theme": "light" | "dark").
 * Also the per-device tools of the board and live screens: browser notifications (through the
 * service worker at /vp-sw.js, which also makes the POS installable), print on accept, kitchen
 * mode, list filters and the manual order form.
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

    /* Language switcher, rendered by the VoxPilot extension as a <template> at the end of the page. */
    function addLanguageMenu() {
        var menu = document.getElementById('menu-mainmenu');
        var template = document.getElementById('vp-language-menu');
        if (!menu || !template || menu.querySelector('.vp-language')) return;
        menu.insertBefore(template.content.cloneNode(true), menu.firstChild);
    }

    function init() {
        apply(root.getAttribute('data-bs-theme') || 'light');
        addToggle();
        addLanguageMenu();
    }

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
            if (!stored()) apply(e.matches ? 'dark' : 'light');
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

(function () {
    /* Per-device preferences (a kitchen tablet and the owner's laptop differ). */
    var prefs = {
        get: function (key) { try { return localStorage.getItem(key) === '1'; } catch (e) { return false; } },
        set: function (key, on) { try { localStorage.setItem(key, on ? '1' : '0'); } catch (e) { /* private mode */ } }
    };

    var registration = null;
    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register('/vp-sw.js', { scope: '/' })
            .then(function (reg) { registration = reg; })
            .catch(function () { /* notifications fall back to the page */ });
    }

    /* A system notification for a new order, when the staff turned them on for this device. */
    window.vpNotify = function (title, body, url) {
        if (!('Notification' in window) || Notification.permission !== 'granted' || !prefs.get('vp-notifications')) return;
        var options = { body: body || '', icon: '/voxpilot/android-chrome-192x192.png', badge: '/voxpilot/favicon-32x32.png', tag: title, data: { url: url || window.location.href } };
        if (registration && registration.showNotification) registration.showNotification(title, options);
        else { try { new Notification(title, options); } catch (e) { /* not supported */ } }
    };

    /* After accepting an order: open its ticket and print it, when print on accept is on. */
    window.vpAfterAccept = function (orderId) {
        if (!prefs.get('vp-print-on-accept') || !window.jQuery || !jQuery.request) return;
        jQuery.request('onOpenOrder', { data: { order_id: orderId } }).done(function () {
            if (window.vpOpenOrderModal) window.vpOpenOrderModal();
            setTimeout(function () { window.print(); }, 400);
        });
    };

    function syncNotificationButton(button) {
        var on = 'Notification' in window && Notification.permission === 'granted' && prefs.get('vp-notifications');
        var blocked = 'Notification' in window && Notification.permission === 'denied';
        button.classList.toggle('on', on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        var label = button.querySelector('span');
        if (!button.dataset.labelOff) button.dataset.labelOff = label.textContent;
        label.textContent = blocked ? button.dataset.labelBlocked : (on ? button.dataset.labelOn : button.dataset.labelOff);
    }

    document.addEventListener('click', function (e) {
        var notify = e.target.closest('[data-vp-notifications]');
        if (notify) {
            if (!('Notification' in window)) return;
            if (prefs.get('vp-notifications') && Notification.permission === 'granted') {
                prefs.set('vp-notifications', false);
                syncNotificationButton(notify);
                return;
            }
            Notification.requestPermission().then(function (permission) {
                prefs.set('vp-notifications', permission === 'granted');
                syncNotificationButton(notify);
            });
            return;
        }

        var add = e.target.closest('[data-vp-add-line]');
        if (add) {
            var lines = add.parentNode.querySelector('[data-vp-lines]');
            var first = lines.querySelector('[data-vp-line]');
            var index = lines.querySelectorAll('[data-vp-line]').length;
            if (index >= 30) return;
            var line = first.cloneNode(true);
            line.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/items\[\d+\]/, 'items[' + index + ']');
                input.value = input.type === 'number' ? '1' : '';
            });
            lines.appendChild(line);
            line.querySelector('select').focus();
            return;
        }

        var remove = e.target.closest('[data-vp-remove-line]');
        if (remove) {
            var all = remove.closest('[data-vp-lines]').querySelectorAll('[data-vp-line]');
            if (all.length > 1) remove.closest('[data-vp-line]').remove();
            else remove.closest('[data-vp-line]').querySelector('select').value = '';
        }
    });

    document.addEventListener('change', function (e) {
        var pref = e.target.closest('[data-vp-pref]');
        if (pref) prefs.set(pref.getAttribute('data-vp-pref'), pref.checked);

        if (e.target.name === 'type' && e.target.closest('.vp-manual')) {
            var form = e.target.closest('.vp-manual');
            form.querySelectorAll('input[name=type]').forEach(function (radio) { radio.parentNode.classList.toggle('active', radio.checked); });
            var address = form.querySelector('[data-vp-delivery-only]');
            if (address) address.hidden = e.target.value !== 'delivery';
        }
    });

    document.addEventListener('input', function (e) {
        var filter = e.target.closest('[data-vp-filter]');
        if (!filter) return;
        var query = filter.value.trim().toLowerCase();
        document.querySelectorAll(filter.getAttribute('data-vp-filter')).forEach(function (item) {
            item.hidden = !!query && (item.getAttribute('data-name') || '').indexOf(query) === -1;
        });
    });

    function init() {
        document.querySelectorAll('[data-vp-pref]').forEach(function (input) { input.checked = prefs.get(input.getAttribute('data-vp-pref')); });
        document.querySelectorAll('[data-vp-notifications]').forEach(syncNotificationButton);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
