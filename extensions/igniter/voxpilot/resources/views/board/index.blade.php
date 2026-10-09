<div class="vp-page">
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
            <button type="button" class="btn btn-light" data-request="onRefresh" data-vp-board-refresh>
                <i class="fa fa-rotate"></i> @lang('igniter.voxpilot::board.refresh')
            </button>
            <a class="btn btn-light" href="{{ admin_url('orders') }}"><i class="fa fa-list"></i> @lang('igniter.voxpilot::board.list_view')</a>
        </div>
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
    // Board updates after a move or refresh replace #vp-board: keep the filter.
    new MutationObserver(applyFilter).observe(document.getElementById('vp-board'), { childList: true });
    // New phone orders arrive on their own: refresh every 20 seconds while the tab is visible.
    setInterval(function () {
        if (!document.hidden) {
            var refresh = document.querySelector('[data-vp-board-refresh]');
            if (refresh) refresh.click();
        }
    }, 20000);
})();
</script>
