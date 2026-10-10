/*
 * VoxPilot POS service worker. It makes the POS installable and shows new-order notifications
 * (the admin pages call registration.showNotification). It does not cache anything: the POS is
 * always live data behind a login.
 */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var target = new URL((event.notification.data && event.notification.data.url) || '/admin/igniter/voxpilot/board', self.location.origin);
    // Only ever open this POS's own pages.
    if (target.origin !== self.location.origin) target = new URL('/admin/igniter/voxpilot/board', self.location.origin);

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
        for (var i = 0; i < clients.length; i++) {
            if (clients[i].url.indexOf(target.pathname) !== -1 && 'focus' in clients[i]) return clients[i].focus();
        }
        return self.clients.openWindow(target.href);
    }));
});
