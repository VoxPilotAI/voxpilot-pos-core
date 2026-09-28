<div class="container-fluid py-3">
    @if(!$tenant)
        <div class="alert alert-warning">
            No tenant configured. Run: <code>php artisan voxpilot:bootstrap-tenant</code>
        </div>
    @else
        {{-- Toast container --}}
        <div id="vp-toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;max-width:400px;"></div>

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">
                <i class="fa fa-headset me-2"></i>VoxPilot Incoming Orders
            </h4>
            <div class="d-flex align-items-center gap-3">
                <select id="vp-location-filter" class="form-select form-select-sm" style="width:auto;">
                    <option value="">All Locations</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->location_id }}">{{ $loc->location_name }}</option>
                    @endforeach
                </select>
                <div id="vp-connection-status" class="badge bg-secondary">
                    <i class="fa fa-circle me-1"></i>Initializing…
                </div>
                <label class="form-check form-switch mb-0" title="Sound notification">
                    <input type="checkbox" class="form-check-input" id="vp-sound-toggle" checked>
                    <span class="form-check-label"><i class="fa fa-volume-up"></i></span>
                </label>
            </div>
        </div>

        {{-- Orders table --}}
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="vp-orders-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;"></th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Source</th>
                            <th>Location</th>
                            <th>Time</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="vp-orders-body">
                        @foreach($recentOrders as $meta)
                            @if($meta->order)
                                <tr data-order-id="{{ $meta->order->order_id }}" data-location-id="{{ $meta->location_id }}">
                                    <td>
                                        @if($meta->order->order_type === 'delivery')
                                            <span class="badge bg-info" title="Delivery"><i class="fa fa-motorcycle"></i></span>
                                        @else
                                            <span class="badge bg-success" title="Pickup"><i class="fa fa-shopping-bag"></i></span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>#{{ $meta->order->order_id }}</strong>
                                        <br><small class="text-muted">{{ $meta->external_order_id }}</small>
                                    </td>
                                    <td>
                                        {{ trim($meta->order->first_name . ' ' . $meta->order->last_name) }}
                                        @if($meta->order->telephone)
                                            <br><small class="text-muted">{{ $meta->order->telephone }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @foreach($meta->order->menus ?? [] as $menu)
                                            <div>{{ $menu->quantity }}× {{ $menu->name }}</div>
                                        @endforeach
                                    </td>
                                    <td><strong>{{ number_format($meta->order->order_total, 2) }}</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $meta->source === 'voice' ? 'primary' : 'secondary' }}">
                                            {{ ucfirst($meta->source) }}
                                        </span>
                                    </td>
                                    <td>{{ $meta->order->location?->location_name ?? '—' }}</td>
                                    <td>
                                        <span title="{{ $meta->created_at }}">
                                            {{ $meta->created_at?->diffForHumans() }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ admin_url('orders/edit/' . $meta->order->order_id) }}"
                                           class="btn btn-sm btn-outline-primary" title="View Order">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted text-center" id="vp-empty-state"
                 style="{{ $recentOrders->count() ? 'display:none;' : '' }}">
                <i class="fa fa-headset fa-2x mb-2 d-block"></i>
                No VoxPilot orders yet. Waiting for incoming calls…
            </div>
        </div>
    @endif
</div>

@if($tenant && $reverbConfig)
{{-- Load Pusher + Echo from CDN (Reverb uses Pusher protocol) --}}
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.17.1/dist/echo.iife.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tenantId = @json($tenantId);
    var locations = @json($locations->pluck('location_id'));
    var reverbConfig = @json($reverbConfig);
    var statusEl = document.getElementById('vp-connection-status');
    var tbody = document.getElementById('vp-orders-body');
    var emptyState = document.getElementById('vp-empty-state');
    var locationFilter = document.getElementById('vp-location-filter');
    var soundToggle = document.getElementById('vp-sound-toggle');

    // ── Audio ──
    var audioCtx = null;
    function playNotificationSound() {
        if (!soundToggle.checked) return;
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.frequency.value = 880;
            osc.type = 'sine';
            gain.gain.value = 0.3;
            osc.start();
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5);
            osc.stop(audioCtx.currentTime + 0.5);
        } catch(e) { console.warn('Sound failed:', e); }
    }

    // ── Toast ──
    function showToast(data) {
        var container = document.getElementById('vp-toast-container');
        var toast = document.createElement('div');
        toast.className = 'alert alert-success alert-dismissible fade show shadow';
        toast.innerHTML = '<strong><i class="fa fa-bell me-1"></i> New Order #' + data.order_id + '</strong>' +
            '<br>' + data.customer.name +
            (data.customer.phone ? ' · ' + data.customer.phone : '') +
            '<br><small>' + data.items.length + ' item(s) · ' + parseFloat(data.order_total).toFixed(2) + '</small>' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        container.prepend(toast);
        setTimeout(function() { toast.remove(); }, 8000);
    }

    // ── Add order row ──
    function addOrderRow(data, animate) {
        if (emptyState) emptyState.style.display = 'none';

        var typeIcon = data.fulfillment_type === 'delivery'
            ? '<span class="badge bg-info" title="Delivery"><i class="fa fa-motorcycle"></i></span>'
            : '<span class="badge bg-success" title="Pickup"><i class="fa fa-shopping-bag"></i></span>';

        var itemsHtml = (data.items || []).map(function(i) {
            return '<div>' + i.quantity + '× ' + i.name + '</div>';
        }).join('');

        var sourceBadge = data.source === 'voice' ? 'primary' : 'secondary';
        var locationName = data.location_name || '—';
        var locationId = data.location_id || '';

        var tr = document.createElement('tr');
        tr.setAttribute('data-order-id', data.order_id);
        tr.setAttribute('data-location-id', locationId);
        if (animate) tr.style.backgroundColor = '#d4edda';

        tr.innerHTML = '<td>' + typeIcon + '</td>' +
            '<td><strong>#' + data.order_id + '</strong><br><small class="text-muted">' + (data.external_order_id || '') + '</small></td>' +
            '<td>' + (data.customer ? data.customer.name : '') +
                (data.customer && data.customer.phone ? '<br><small class="text-muted">' + data.customer.phone + '</small>' : '') + '</td>' +
            '<td>' + itemsHtml + '</td>' +
            '<td><strong>' + parseFloat(data.order_total).toFixed(2) + '</strong></td>' +
            '<td><span class="badge bg-' + sourceBadge + '">' + (data.source ? data.source.charAt(0).toUpperCase() + data.source.slice(1) : 'Voice') + '</span></td>' +
            '<td>' + locationName + '</td>' +
            '<td><small>Just now</small></td>' +
            '<td><a href="' + window.location.origin + '/admin/orders/edit/' + data.order_id + '" class="btn btn-sm btn-outline-primary" title="View Order"><i class="fa fa-eye"></i></a></td>';

        tbody.prepend(tr);

        if (animate) {
            setTimeout(function() { tr.style.transition = 'background-color 2s'; tr.style.backgroundColor = ''; }, 100);
        }

        applyLocationFilter();
    }

    // ── Location filter ──
    function applyLocationFilter() {
        var filterVal = locationFilter.value;
        var rows = tbody.querySelectorAll('tr');
        rows.forEach(function(row) {
            if (!filterVal || row.getAttribute('data-location-id') === filterVal) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    locationFilter.addEventListener('change', applyLocationFilter);

    // ── Initialize Echo with Reverb (Pusher protocol) ──
    if (typeof window.Echo === 'undefined' && typeof Echo !== 'undefined') {
        window.Echo = null; // will be set below
    }

    if (!reverbConfig || !reverbConfig.key) {
        statusEl.className = 'badge bg-danger';
        statusEl.innerHTML = '<i class="fa fa-times-circle me-1"></i>Reverb not configured';
        console.error('VoxPilot: Reverb config missing. Check REVERB_APP_KEY in .env');
        return;
    }

    var useTLS = reverbConfig.scheme === 'https';
    var wsHost = reverbConfig.host || window.location.hostname;
    var wsPort = reverbConfig.port || (useTLS ? 443 : 80);

    try {
        var echo = new Echo({
            broadcaster: 'pusher',
            key: reverbConfig.key,
            wsHost: wsHost,
            wsPort: wsPort,
            wssPort: wsPort,
            forceTLS: useTLS,
            encrypted: useTLS,
            disableStats: true,
            enabledTransports: ['ws', 'wss'],
            cluster: 'mt1',
            authEndpoint: reverbConfig.authEndpoint,
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                }
            }
        });
    } catch(e) {
        statusEl.className = 'badge bg-danger';
        statusEl.innerHTML = '<i class="fa fa-times-circle me-1"></i>Echo init failed';
        console.error('VoxPilot Echo init error:', e);
        return;
    }

    // ── Connection state tracking ──
    if (echo.connector && echo.connector.pusher) {
        var pusher = echo.connector.pusher;
        pusher.connection.bind('connected', function() {
            console.log('VoxPilot: WebSocket connected');
        });
        pusher.connection.bind('error', function(err) {
            statusEl.className = 'badge bg-danger';
            statusEl.innerHTML = '<i class="fa fa-times-circle me-1"></i>Disconnected';
            console.error('VoxPilot: WebSocket error:', err);
        });
        pusher.connection.bind('disconnected', function() {
            statusEl.className = 'badge bg-danger';
            statusEl.innerHTML = '<i class="fa fa-times-circle me-1"></i>Disconnected';
        });
        pusher.connection.bind('unavailable', function() {
            statusEl.className = 'badge bg-warning';
            statusEl.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i>Reconnecting…';
        });
    }

    // ── Subscribe to tenant location channels ──
    var subscribed = 0;
    var subscriptionErrors = 0;

    locations.forEach(function(locId) {
        var channelName = 'tenant.' + tenantId + '.location.' + locId + '.orders';
        console.log('VoxPilot: subscribing to private-' + channelName);

        echo.private(channelName)
            .listen('.voxpilot.order.created', function(data) {
                console.log('VoxPilot: order received', data);
                playNotificationSound();
                showToast(data);
                addOrderRow(data, true);
            })
            .error(function(err) {
                subscriptionErrors++;
                console.error('VoxPilot: channel auth error for', channelName, err);
                if (subscriptionErrors >= locations.length) {
                    statusEl.className = 'badge bg-danger';
                    statusEl.innerHTML = '<i class="fa fa-times-circle me-1"></i>Auth failed';
                }
            });
        subscribed++;
    });

    if (subscribed > 0) {
        statusEl.className = 'badge bg-success';
        statusEl.innerHTML = '<i class="fa fa-circle me-1"></i>Listening (' + subscribed + ')';
    } else {
        statusEl.className = 'badge bg-warning';
        statusEl.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i>No locations';
    }
});
</script>
@endif
