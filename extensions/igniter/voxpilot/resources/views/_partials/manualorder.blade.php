<form class="vp-manual" data-request="onCreateManualOrder" novalidate>
    <div class="modal-header vp-om-header">
        <div>
            <div class="vp-om-eyebrow">@lang('igniter.voxpilot::orders.new_order')</div>
            <h2 class="modal-title vp-om-title" id="vp-order-modal-title">@lang('igniter.voxpilot::orders.manual_title')</h2>
            <p class="vp-page-sub">@lang('igniter.voxpilot::orders.manual_sub')</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('igniter.voxpilot::orders.close')"></button>
    </div>

    <div class="modal-body vp-om-body">
        <div class="vp-manual-grid">
            <label class="vp-field">
                <span>@lang('igniter.voxpilot::orders.customer_name')</span>
                <input type="text" class="form-control" name="customer_name" maxlength="96" autocomplete="off">
            </label>
            <label class="vp-field">
                <span>@lang('igniter.voxpilot::orders.phone')</span>
                <input type="tel" class="form-control" name="telephone" maxlength="32" autocomplete="off">
            </label>
        </div>

        <div class="vp-field">
            <span>@lang('igniter.voxpilot::orders.type')</span>
            <div class="vp-segmented" role="radiogroup" aria-label="@lang('igniter.voxpilot::orders.type')">
                <label class="{{ $store && !$store['collection_enabled'] ? '' : 'active' }}"><input type="radio" name="type" value="pickup" class="visually-hidden" @checked(!$store || $store['collection_enabled'])> <i class="fa fa-bag-shopping"></i> @lang('igniter.voxpilot::live.pickup')</label>
                <label class="{{ $store && !$store['collection_enabled'] ? 'active' : '' }}"><input type="radio" name="type" value="delivery" class="visually-hidden" @checked($store && !$store['collection_enabled'])> <i class="fa fa-motorcycle"></i> @lang('igniter.voxpilot::live.delivery')</label>
            </div>
        </div>

        <label class="vp-field" data-vp-delivery-only hidden>
            <span>@lang('igniter.voxpilot::orders.address')</span>
            <input type="text" class="form-control" name="address" maxlength="255" autocomplete="off">
        </label>

        <div class="vp-field">
            <span>@lang('igniter.voxpilot::orders.items')</span>
            <div class="vp-manual-lines" data-vp-lines>
                <div class="vp-manual-line" data-vp-line>
                    <select class="form-select" name="items[0][menu_id]" aria-label="@lang('igniter.voxpilot::orders.item')">
                        <option value="">@lang('igniter.voxpilot::orders.item')…</option>
                        @foreach($items->sortBy([['category', 'asc'], ['name', 'asc']]) as $item)
                            <option value="{{ $item['id'] }}" @disabled($item['sold_out'])>{{ $item['name'] }} · {{ currency_format($item['price']) }}@if($item['out_of_stock']) · @lang('igniter.voxpilot::board.out_of_stock')@elseif($item['unavailable_today']) · @lang('igniter.voxpilot::board.sold_out')@endif</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control" name="items[0][quantity]" value="1" min="1" max="99" aria-label="@lang('igniter.voxpilot::orders.quantity')">
                    <button type="button" class="btn btn-light" data-vp-remove-line aria-label="@lang('igniter.voxpilot::orders.remove_item')"><i class="fa fa-xmark"></i></button>
                </div>
            </div>
            <button type="button" class="btn btn-light vp-manual-add" data-vp-add-line><i class="fa fa-plus"></i> @lang('igniter.voxpilot::orders.add_item')</button>
        </div>

        <label class="vp-field">
            <span>@lang('igniter.voxpilot::orders.notes')</span>
            <textarea class="form-control" name="notes" rows="2" maxlength="500"></textarea>
        </label>
    </div>

    <div class="modal-footer vp-om-footer">
        <span></span>
        <button type="submit" class="btn btn-primary vp-om-next"><i class="fa fa-check"></i> @lang('igniter.voxpilot::orders.create_order')</button>
    </div>
</form>
