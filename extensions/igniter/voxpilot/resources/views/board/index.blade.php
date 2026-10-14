<div class="vp-page {{ $kds ? 'vp-kds-page' : '' }}" data-vp-board-page @if($kds) data-vp-kds @endif>
    <div class="vp-page-head">
        <div>
            <h1 class="vp-page-title">@lang('igniter.voxpilot::board.title')</h1>
            <p class="vp-page-sub">@lang('igniter.voxpilot::board.subtitle')</p>
        </div>
        <div class="vp-page-actions">
            <div class="vp-segmented" role="group" aria-label="@lang('igniter.voxpilot::board.filter_source')" data-vp-board-filter>
                <button type="button" class="active" data-source="all">@lang('igniter.voxpilot::board.all')</button>
                <button type="button" data-source="phone">@lang('igniter.voxpilot::dashboard.ai_phone')</button>
                <button type="button" data-source="other">@lang('igniter.voxpilot::dashboard.other_channels')</button>
            </div>
            <button type="button" class="btn btn-light" data-request="onRefresh" data-vp-board-refresh aria-label="@lang('igniter.voxpilot::board.refresh')">
                <i class="fa fa-rotate"></i><span class="vp-hide-kds"> @lang('igniter.voxpilot::board.refresh')</span>
            </button>
            <a class="btn btn-light vp-hide-kds" href="{{ admin_url('orders') }}"><i class="fa fa-list"></i> @lang('igniter.voxpilot::board.list_view')</a>
            <div class="dropdown">
                <button type="button" class="btn btn-light" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="@lang('igniter.voxpilot::board.device_settings')">
                    <i class="fa fa-sliders"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end vp-device-menu">
                    <label class="vp-device-option"><input type="checkbox" class="form-check-input" data-vp-pref="vp-print-on-accept"> @lang('igniter.voxpilot::board.print_on_accept')</label>
                    <button type="button" class="vp-device-option" data-vp-notifications
                            data-label-on="@lang('igniter.voxpilot::board.notifications_on')"
                            data-label-blocked="@lang('igniter.voxpilot::board.notifications_blocked')">
                        <i class="fa fa-bell"></i> <span>@lang('igniter.voxpilot::board.notifications')</span>
                    </button>
                </div>
            </div>
            <a class="btn btn-light" href="{{ admin_url('igniter/voxpilot/board'.($kds ? '' : '?kds=1')) }}">
                <i class="fa {{ $kds ? 'fa-compress' : 'fa-expand' }}"></i> {{ $kds ? lang('igniter.voxpilot::board.exit_kitchen') : lang('igniter.voxpilot::board.kitchen_mode') }}
            </a>
            <button type="button" class="btn btn-primary" data-request="onOpenManualOrder" data-request-success="vpOpenOrderModal()">
                <i class="fa fa-plus"></i> @lang('igniter.voxpilot::orders.new_order')
            </button>
        </div>
    </div>

    <div id="vp-store-bar">
        {!! $this->makePartial('storebar', ['store' => $store, 'soldOut' => $soldOut]) !!}
    </div>

    {!! $this->makePartial('ordermodalshell') !!}

    <div id="vp-board">
        {!! $this->makePartial('columns', ['columns' => $columns]) !!}
    </div>
</div>

<script>
(function () {
    var filter = 'all';
    function applyFilter() {
        document.querySelectorAll('#vp-board [data-vp-card]').forEach(function (card) {
            var source = card.getAttribute('data-source');
            card.hidden = !(filter === 'all' || filter === source);
        });
        document.querySelectorAll('#vp-board [data-vp-column]').forEach(function (column) {
            var count = column.querySelectorAll('[data-vp-card]:not([hidden])').length;
            var badge = column.querySelector('[data-vp-count]');
            if (badge) badge.textContent = count;
        });
    }
    document.querySelectorAll('[data-vp-board-filter] button').forEach(function (button) {
        button.addEventListener('click', function () {
            filter = button.getAttribute('data-source');
            document.querySelectorAll('[data-vp-board-filter] button').forEach(function (b) { b.classList.toggle('active', b === button); });
            applyFilter();
        });
    });
    // New phone orders since the last look: a browser notification (when the staff turned them on).
    var seen = null;
    function notifyNewOrders() {
        var cards = document.querySelectorAll('#vp-board [data-vp-card][data-source="phone"] .vp-card-id');
        var ids = Array.prototype.map.call(cards, function (el) { return el.textContent.trim(); });
        if (seen && window.vpNotify) {
            ids.filter(function (id) { return seen.indexOf(id) === -1; }).forEach(function (id) {
                window.vpNotify(@json(lang('igniter.voxpilot::board.new_order_notification', ['id' => ':id'])).replace(':id', id.replace('#', '')), '', window.location.href);
            });
        }
        seen = ids;
    }
    notifyNewOrders();
    // Board updates after a move or refresh replace #vp-board: keep the filter.
    new MutationObserver(function () { applyFilter(); notifyNewOrders(); }).observe(document.getElementById('vp-board'), { childList: true });
    // New phone orders arrive on their own: refresh every 20 seconds while the tab is visible.
    setInterval(function () {
        if (!document.hidden) {
            var refresh = document.querySelector('[data-vp-board-refresh]');
            if (refresh) refresh.click();
        }
    }, 20000);
})();
</script>
