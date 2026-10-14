/*
 * The admin in the staff member's language, also where TastyIgniter's scripts hard-code English:
 * window.vpI18n (printed in <head> by the VoxPilot extension) maps those English texts to the
 * admin's language. Dialogs, the date range picker (ranges, buttons, months and weekdays) and
 * accessible labels ("Close") are swapped; month and day names come from the browser (Intl).
 */
(function () {
    var i18n = window.vpI18n || { locale: 'en', strings: {} };
    var strings = i18n.strings || {};
    var t = function (text) {
        return typeof text === 'string' && Object.prototype.hasOwnProperty.call(strings, text.trim()) ? strings[text.trim()] : text;
    };
    window.vpT = t;

    var nativeConfirm = window.confirm, nativeAlert = window.alert;
    window.confirm = function (message) { return nativeConfirm.call(window, t(message)); };
    window.alert = function (message) { return nativeAlert.call(window, t(message)); };

    /* moment ships English only here: build the admin's locale from Intl. */
    function defineMomentLocale(locale) {
        if (!window.moment || !window.Intl || locale === 'en' || moment.locales().indexOf(locale) !== -1) return;
        try {
            var names = function (options, count, at) {
                var out = [];
                for (var i = 0; i < count; i++) out.push(new Intl.DateTimeFormat(locale, options).format(at(i)));
                return out;
            };
            var month = function (i) { return new Date(Date.UTC(2021, i, 15)); };
            var day = function (i) { return new Date(Date.UTC(2021, 0, 3 + i)); }; // 3 Jan 2021 is a Sunday
            var weekInfo = new Intl.Locale(locale).weekInfo || {};
            moment.defineLocale(locale, {
                months: names({ month: 'long', timeZone: 'UTC' }, 12, month),
                monthsShort: names({ month: 'short', timeZone: 'UTC' }, 12, month).map(function (m) { return m.replace(/\.$/, ''); }),
                weekdays: names({ weekday: 'long', timeZone: 'UTC' }, 7, day),
                weekdaysShort: names({ weekday: 'short', timeZone: 'UTC' }, 7, day),
                weekdaysMin: names({ weekday: 'narrow', timeZone: 'UTC' }, 7, day),
                week: { dow: (weekInfo.firstDay || 1) % 7, doy: 4 }
            });
        } catch (e) { /* keep English dates */ }
        moment.locale('en');
    }

    function patchDateRangePicker() {
        var $ = window.jQuery;
        if (!$ || !$.fn.daterangepicker || $.fn.daterangepicker.vpTranslated) return;
        var original = $.fn.daterangepicker;
        $.fn.daterangepicker = function (options, callback) {
            options = $.extend({}, options);
            if (options.ranges) {
                var ranges = {};
                Object.keys(options.ranges).forEach(function (label) { ranges[t(label)] = options.ranges[label]; });
                options.ranges = ranges;
            }
            var data = window.moment ? moment.localeData(i18n.locale) : null;
            options.locale = $.extend({
                applyLabel: t('Apply'),
                cancelLabel: t('Cancel'),
                customRangeLabel: t('Custom Range'),
                daysOfWeek: data ? data.weekdaysMin() : undefined,
                monthNames: data ? data.monthsShort() : undefined,
                firstDay: data ? data.firstDayOfWeek() : undefined
            }, options.locale);
            return original.call(this, options, callback);
        };
        $.fn.daterangepicker.vpTranslated = true;
        Object.keys(original).forEach(function (key) { $.fn.daterangepicker[key] = original[key]; });
    }

    var ATTRIBUTES = ['aria-label', 'title', 'placeholder'];
    function translateAttributes(root) {
        if (!root || !root.querySelectorAll) return;
        // Screen-reader-only texts ("Toggle Dropdown", "Loading...") hard-coded in TastyIgniter's views.
        var hidden = root.matches && root.matches('.visually-hidden, .sr-only') ? [root] : [];
        hidden.concat(Array.prototype.slice.call(root.querySelectorAll('.visually-hidden, .sr-only'))).forEach(function (el) {
            if (el.children.length === 0 && t(el.textContent) !== el.textContent) el.textContent = t(el.textContent);
        });
        var nodes = [root].concat(Array.prototype.slice.call(root.querySelectorAll('[aria-label],[title],[placeholder]')));
        nodes.forEach(function (el) {
            if (!el.getAttribute) return;
            ATTRIBUTES.forEach(function (name) {
                var value = el.getAttribute(name);
                var translated = value && translateLabel(value);
                if (translated && translated !== value) el.setAttribute(name, translated);
            });
            // Choices.js hard-codes the text of its remove buttons.
            if (el.hasAttribute && el.hasAttribute('data-button') && el.textContent === 'Remove item' && i18n.choices) {
                el.textContent = i18n.choices.remove.replace(/:\s*:value|:value/, '').trim();
            }
        });
    }

    /* Exact texts, then labels built around a value ("Remove item: 'Pizza'"). */
    function translateLabel(value) {
        if (t(value) !== value) return t(value);
        var removeItem = /^Remove item: (.*)$/.exec(value);
        if (removeItem && i18n.choices) return i18n.choices.remove.replace(':value', removeItem[1]);
        return value;
    }

    /* TastyIgniter's select lists (Choices.js) read SelectList.DEFAULTS when they start. */
    function patchSelectList() {
        var $ = window.jQuery, c = i18n.choices;
        if (!$ || !c || !$.fn.selectList || !$.fn.selectList.Constructor) return;
        var esc = function (value) { return String(value).replace(/[&<>"']/g, function (ch) { return '&#' + ch.charCodeAt(0) + ';'; }); };
        $.extend($.fn.selectList.Constructor.DEFAULTS, {
            loadingText: t('Loading...'),
            noResultsText: c.no_results,
            noChoicesText: c.no_choices,
            itemSelectText: c.select,
            uniqueItemText: c.unique,
            customAddItemText: c.custom_add,
            addItemText: function (value) { return c.add.replace(':value', esc(value)); },
            maxItemText: function (count) { return c.max.replace(':count', count); },
            removeItemLabelText: function (value) { return c.remove.replace(':value', value); }
        });
    }

    /* The rich text editor (Summernote) has its own language files, published with TastyIgniter. */
    function localizeRichEditor() {
        var $ = window.jQuery;
        var tag = { es: 'es-ES', de: 'de-DE', fr: 'fr-FR', it: 'it-IT', pt: 'pt-PT', nl: 'nl-NL' }[i18n.locale];
        if (!$ || !$.summernote || !tag) return;
        if ($.fn.richEditor && $.fn.richEditor.Constructor) $.fn.richEditor.Constructor.DEFAULTS.lang = tag;
        // Loaded before the editors start (this script runs while the page is still parsing).
        if (!$.summernote.lang[tag] && document.readyState === 'loading') {
            document.write('<script src="/vendor/igniter/js/locales/summernote/summernote-' + tag + '.min.js"><\/script>');
        }
    }

    defineMomentLocale(i18n.locale);
    if (window.moment && moment.locales().indexOf(i18n.locale) !== -1) moment.locale(i18n.locale);
    patchDateRangePicker();
    patchSelectList();
    localizeRichEditor();

    function init() {
        translateAttributes(document.body);
        if (window.MutationObserver) {
            new MutationObserver(function (changes) {
                changes.forEach(function (change) {
                    if (change.type === 'attributes') translateAttributes(change.target);
                    else change.addedNodes.forEach(translateAttributes);
                });
            }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ATTRIBUTES });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

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
        button.setAttribute('aria-label', window.vpT ? window.vpT('Switch light and dark mode') : 'Switch light and dark mode');
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
