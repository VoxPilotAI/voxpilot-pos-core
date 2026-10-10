<div class="modal-header vp-om-header">
    <div>
        <div class="vp-om-eyebrow">{{ $locationName }}</div>
        <h2 class="modal-title vp-om-title" id="vp-order-modal-title">@lang('igniter.voxpilot::board.availability_title')</h2>
        <p class="vp-page-sub">@lang('igniter.voxpilot::board.availability_sub')</p>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('igniter.voxpilot::orders.close')"></button>
</div>

<div class="modal-body vp-om-body">
    @if($items->isEmpty())
        <p class="vp-empty">@lang('igniter.voxpilot::board.no_items')</p>
    @else
        <input type="search" class="form-control" placeholder="@lang('igniter.voxpilot::orders.search_items')"
               aria-label="@lang('igniter.voxpilot::orders.search_items')" data-vp-filter="#vp-availability [data-vp-item]">
        <ul class="vp-avail" id="vp-availability">
            @foreach($items->sortBy([['sold_out', 'desc'], ['category', 'asc'], ['name', 'asc']]) as $item)
                <li data-vp-item data-name="{{ mb_strtolower($item['name'].' '.$item['category']) }}">
                    <div>
                        <strong>{{ $item['name'] }}</strong>
                        <span class="vp-om-time">{{ $item['category'] }}@if($item['category']) · @endif{{ currency_format($item['price']) }}</span>
                    </div>
                    @if($item['out_of_stock'])
                        <span class="vp-pill vp-pill-danger" title="@lang('igniter.voxpilot::board.out_of_stock_help')">@lang('igniter.voxpilot::board.out_of_stock')</span>
                    @else
                        <button type="button" class="vp-switch {{ $item['unavailable_today'] ? '' : 'on' }}" role="switch" aria-checked="{{ $item['unavailable_today'] ? 'false' : 'true' }}"
                                data-request="onSetUnavailable" data-request-data="menu_id: {{ $item['id'] }}, unavailable: {{ $item['unavailable_today'] ? 0 : 1 }}">
                            <span class="{{ $item['unavailable_today'] ? 'vp-text-danger' : 'vp-text-success' }}">{{ $item['unavailable_today'] ? lang('igniter.voxpilot::board.sold_out') : lang('igniter.voxpilot::board.in_stock') }}</span>
                            <span class="vp-switch-track" aria-hidden="true"><span></span></span>
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
