<div class="vp-page vp-live">
    @if(!$tenant)
        <div class="alert alert-warning">@lang('igniter.voxpilot::live.no_tenant')</div>
    @else
        <div class="vp-page-head">
            <div>
                <h1 class="vp-page-title">@lang('igniter.voxpilot::board.nav_live')</h1>
                <p class="vp-page-sub">@lang('igniter.voxpilot::live.subtitle')</p>
            </div>
            <div class="vp-page-actions">
                @if($locations->count() > 1)
                    <select id="vp-location-filter" class="form-select" style="width: auto" aria-label="@lang('igniter.voxpilot::live.location')">
                        <option value="">@lang('igniter.voxpilot::live.all_locations')</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->location_id }}">{{ $loc->location_name }}</option>
                        @endforeach
                    </select>
                @endif
                <span id="vp-connection-status" class="vp-pill vp-pill-muted vp-live-status"><i class="vp-dot vp-bg-accent"></i>@lang('igniter.voxpilot::live.connecting')</span>
                <span class="vp-live-clock" id="vp-live-clock" aria-live="off"></span>
                <label class="vp-sound" title="@lang('igniter.voxpilot::live.sound')">
                    <input type="checkbox" id="vp-sound-toggle" checked>
                    <i class="fa fa-volume-high" aria-hidden="true"></i>
                    <span class="visually-hidden">@lang('igniter.voxpilot::live.sound')</span>
                </label>
            </div>
        </div>

        {!! $this->makePartial('ordermodalshell') !!}

        <div class="vp-live-grid" id="vp-orders-body">
            @foreach($recentOrders as $meta)
                @if($meta->order)
                    <article class="vp-ticket" data-order-id="{{ $meta->order->order_id }}" data-location-id="{{ $meta->location_id }}">
                        <div class="vp-ticket-head">
                            <button type="button" class="vp-card-open"
                                    data-request="onOpenOrder"
                                    data-request-data="order_id: {{ $meta->order->order_id }}"
                                    data-request-success="vpOpenOrderModal()">
                                <span class="vp-ticket-id">#{{ $meta->order->order_id }}</span>
                                <span class="vp-ticket-customer">{{ trim($meta->order->first_name.' '.$meta->order->last_name) }}@if($meta->order->telephone) · {{ $meta->order->telephone }}@endif</span>
                            </button>
                            <div class="vp-ticket-badges">
                                {!! $this->makePartial('orderstatuspill', ['order' => $meta->order]) !!}
                                <span class="vp-pill {{ $meta->order->order_type === 'delivery' ? 'vp-pill-primary' : 'vp-pill-success' }}">
                                    <i class="fa {{ $meta->order->order_type === 'delivery' ? 'fa-motorcycle' : 'fa-bag-shopping' }}"></i>
                                    {{ $meta->order->order_type === 'delivery' ? lang('igniter.voxpilot::live.delivery') : lang('igniter.voxpilot::live.pickup') }}
                                </span>
                            </div>
                        </div>
                        <ul class="vp-ticket-lines">
                            @foreach($meta->order->menus ?? [] as $menu)
                                <li><span><strong>{{ $menu->quantity }}×</strong> {{ $menu->name }}</span><span>{{ currency_format($menu->subtotal) }}</span></li>
                            @endforeach
                        </ul>
                        <div class="vp-ticket-foot">
                            <span class="vp-ticket-time" title="{{ $meta->created_at }}"><i class="fa fa-phone"></i> {{ $meta->created_at?->diffForHumans() }}</span>
                            <strong>{{ currency_format($meta->order->order_total) }}</strong>
                        </div>
                        {!! $this->makePartial('ticketactions', ['order' => $meta->order]) !!}
                    </article>
                @endif
            @endforeach
        </div>

        <div class="vp-live-empty" id="vp-empty-state" @if($recentOrders->count()) hidden @endif>
            <div class="vp-live-wave" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
            <p>@lang('igniter.voxpilot::live.empty')</p>
        </div>
    @endif
</div>

@if($tenant)
<script>
(function () {
    var clock = document.getElementById('vp-live-clock');
    function tick() { if (clock) clock.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
    tick(); setInterval(tick, 15000);
})();
</script>
@endif

@if($tenant && $reverbConfig)
{{-- Reverb speaks the Pusher protocol --}}
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.17.1/dist/echo.iife.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tenantId = @json($tenantId);
    var locations = @json($locations->pluck('location_id'));
    var reverbConfig = @json($reverbConfig);
    var t = @json(trans('igniter.voxpilot::live'));
    var statusEl = document.getElementById('vp-connection-status');
    var grid = document.getElementById('vp-orders-body');
    var emptyState = document.getElementById('vp-empty-state');
    var locationFilter = document.getElementById('vp-location-filter');
    var soundToggle = document.getElementById('vp-sound-toggle');
    var money = new Intl.NumberFormat(document.documentElement.lang || undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function setStatus(tone, text) {
        statusEl.className = 'vp-pill vp-live-status vp-pill-' + tone;
        statusEl.innerHTML = '';
        var dot = document.createElement('i');
        dot.className = 'vp-dot vp-bg-' + (tone === 'success' ? 'success' : tone === 'danger' ? 'danger' : 'accent');
        statusEl.appendChild(dot);
        statusEl.appendChild(document.createTextNode(text));
    }

    var audioCtx = null;
    function chime() {
        if (!soundToggle || !soundToggle.checked) return;
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            [880, 1175].forEach(function (freq, i) {
                var osc = audioCtx.createOscillator(), gain = audioCtx.createGain();
                osc.connect(gain); gain.connect(audioCtx.destination);
                osc.frequency.value = freq; osc.type = 'sine';
                var start = audioCtx.currentTime + i * 0.18;
                gain.gain.setValueAtTime(0.25, start);
                gain.gain.exponentialRampToValueAtTime(0.001, start + 0.4);
                osc.start(start); osc.stop(start + 0.4);
            });
        } catch (e) { /* audio blocked until the first click */ }
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    // Built with textContent only: customer names and items come from phone calls.
    function addTicket(data) {
        if (emptyState) emptyState.hidden = true;
        var delivery = data.fulfillment_type === 'delivery';
        var card = el('article', 'vp-ticket vp-ticket-new');
        card.setAttribute('data-order-id', data.order_id);
        card.setAttribute('data-location-id', data.location_id || '');

        var orderId = parseInt(data.order_id, 10);
        var head = el('div', 'vp-ticket-head');
        var open = el('button', 'vp-card-open');
        open.type = 'button';
        open.setAttribute('data-request', 'onOpenOrder');
        open.setAttribute('data-request-data', 'order_id: ' + orderId);
        open.setAttribute('data-request-success', 'vpOpenOrderModal()');
        open.appendChild(el('span', 'vp-ticket-id', '#' + orderId));
        var customer = data.customer ? data.customer.name + (data.customer.phone ? ' · ' + data.customer.phone : '') : '';
        open.appendChild(el('span', 'vp-ticket-customer', customer));
        head.appendChild(open);
        var badges = el('div', 'vp-ticket-badges');
        var pill = el('span', 'vp-pill vp-pill-muted');
        pill.id = 'vp-ticket-status-' + orderId;
        badges.appendChild(pill);
        badges.appendChild(el('span', 'vp-pill ' + (delivery ? 'vp-pill-primary' : 'vp-pill-success'), delivery ? t.delivery : t.pickup));
        head.appendChild(badges);
        card.appendChild(head);

        var lines = el('ul', 'vp-ticket-lines');
        (data.items || []).forEach(function (item) {
            var li = el('li');
            var name = el('span');
            name.appendChild(el('strong', null, item.quantity + '× '));
            name.appendChild(document.createTextNode(item.name));
            li.appendChild(name);
            li.appendChild(el('span', null, item.subtotal != null ? money.format(item.subtotal) : ''));
            lines.appendChild(li);
        });
        card.appendChild(lines);

        var foot = el('div', 'vp-ticket-foot');
        foot.appendChild(el('span', 'vp-ticket-time', t.just_now));
        foot.appendChild(el('strong', null, money.format(parseFloat(data.order_total) || 0)));
        card.appendChild(foot);
        var actions = el('div', 'vp-ticket-actions');
        actions.id = 'vp-ticket-actions-' + orderId;
        card.appendChild(actions);

        grid.prepend(card);
        // Status pill and Accept/Details come from the server, in the admin's language.
        if (window.jQuery && jQuery.request) jQuery.request('onTicketExtras', { data: { order_id: orderId } });
        setTimeout(function () { card.classList.remove('vp-ticket-new'); }, 60000);
        applyLocationFilter();
    }

    // Like a kitchen printer: keep chiming every 30 s while an order waits to be accepted.
    setInterval(function () {
        if (document.querySelector('#vp-orders-body .vp-ticket-accept')) chime();
    }, 30000);

    function applyLocationFilter() {
        if (!locationFilter) return;
        var value = locationFilter.value;
        grid.querySelectorAll('[data-order-id]').forEach(function (card) {
            card.hidden = !!value && card.getAttribute('data-location-id') !== value;
        });
    }
    if (locationFilter) locationFilter.addEventListener('change', applyLocationFilter);

    if (!reverbConfig || !reverbConfig.key) { setStatus('danger', t.not_configured); return; }

    var useTLS = reverbConfig.scheme === 'https';
    var wsPort = reverbConfig.port || (useTLS ? 443 : 80);
    var echo;
    try {
        echo = new Echo({
            broadcaster: 'pusher', key: reverbConfig.key,
            wsHost: reverbConfig.host || window.location.hostname, wsPort: wsPort, wssPort: wsPort,
            forceTLS: useTLS, encrypted: useTLS, disableStats: true, enabledTransports: ['ws', 'wss'], cluster: 'mt1',
            authEndpoint: reverbConfig.authEndpoint,
            auth: { headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' } }
        });
    } catch (e) { setStatus('danger', t.disconnected); return; }

    if (echo.connector && echo.connector.pusher) {
        var connection = echo.connector.pusher.connection;
        connection.bind('connected', function () { setStatus('success', t.live); });
        connection.bind('error', function () { setStatus('danger', t.disconnected); });
        connection.bind('disconnected', function () { setStatus('danger', t.disconnected); });
        connection.bind('unavailable', function () { setStatus('muted', t.reconnecting); });
    }

    var failures = 0;
    locations.forEach(function (locationId) {
        echo.private('tenant.' + tenantId + '.location.' + locationId + '.orders')
            .listen('.voxpilot.order.created', function (data) {
                chime(); addTicket(data);
                // Only the order number: notifications show on lock screens.
                if (window.vpNotify) window.vpNotify(@json(lang('igniter.voxpilot::board.new_order_notification', ['id' => ':id'])).replace(':id', data.order_id), '', window.location.href);
            })
            .error(function () { failures++; if (failures >= locations.length) setStatus('danger', t.auth_failed); });
    });
    if (!locations.length) setStatus('muted', t.no_locations);
});
</script>
@endif
