@php($label = fn ($name) => \Igniter\VoxPilot\Http\Controllers\Concerns\OrderQuickActions::statusLabel($name))
<div class="modal-header vp-om-header">
    <div>
        <div class="vp-om-eyebrow">{{ $phone ? lang('igniter.voxpilot::orders.confirmed_by_phone') : lang('igniter.voxpilot::orders.order') }}</div>
        <h2 class="modal-title vp-om-title" id="vp-order-modal-title">#{{ $order->order_id }} · {{ trim($order->first_name.' '.$order->last_name) ?: '—' }}</h2>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('igniter.voxpilot::orders.close')"></button>
</div>

<div class="modal-body vp-om-body">
    <div class="vp-print-ticket">
        <div class="vp-om-meta">
            <span class="vp-pill vp-pill-status" style="--vp-status: {{ $order->status?->status_color ?: '#8a96b4' }}">{{ $label($order->status?->status_name) }}</span>
            <span class="vp-pill {{ $order->order_type === 'delivery' ? 'vp-pill-primary' : 'vp-pill-success' }}">
                <i class="fa {{ $order->order_type === 'delivery' ? 'fa-motorcycle' : 'fa-bag-shopping' }}"></i>
                {{ $order->order_type === 'delivery' ? lang('igniter.voxpilot::live.delivery') : lang('igniter.voxpilot::live.pickup') }}
            </span>
            <span class="vp-om-time"><i class="fa fa-clock"></i> {{ $order->created_at?->format('d M H:i') }} · {{ $order->created_at?->diffForHumans() }}</span>
        </div>

        <ul class="vp-om-lines">
            @foreach($order->menus as $menu)
                <li>
                    <span><strong>{{ $menu->quantity }}×</strong> {{ $menu->name }}@if($menu->comment)<em>{{ $menu->comment }}</em>@endif</span>
                    <span>{{ currency_format($menu->subtotal) }}</span>
                </li>
            @endforeach
            <li class="vp-om-total"><span>@lang('igniter.voxpilot::dashboard.total')</span><span>{{ currency_format($order->order_total) }}</span></li>
        </ul>

        @if($order->comment)
            <div class="vp-card-note"><i class="fa fa-note-sticky"></i> {{ $order->comment }}</div>
        @endif

        <dl class="vp-om-facts">
            @if($order->telephone)
                <div><dt>@lang('igniter.voxpilot::orders.phone')</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $order->telephone) }}">{{ $order->telephone }}</a></dd></div>
            @endif
            @if($order->order_type === 'delivery' && $order->formatted_address)
                <div><dt>@lang('igniter.voxpilot::orders.address')</dt><dd>{{ strip_tags((string) $order->formatted_address) }}</dd></div>
            @endif
            @if($order->payment_method)
                <div><dt>@lang('igniter.voxpilot::orders.payment')</dt><dd>{{ $order->payment_method->name }}</dd></div>
            @endif
        </dl>
    </div>

    @unless($isCanceled)
        <div class="vp-om-section">
            <div class="vp-om-section-title">@lang('igniter.voxpilot::orders.change_status')</div>
            <div class="vp-om-statuses">
                @foreach($statuses as $status)
                    <button type="button"
                            class="vp-om-status {{ (int) $status->status_id === (int) $order->status_id ? 'active' : '' }}"
                            style="--vp-status: {{ $status->status_color ?: '#8a96b4' }}"
                            @if((int) $status->status_id === (int) $order->status_id) aria-pressed="true" disabled @endif
                            data-request="onSetOrderStatus"
                            data-request-data="order_id: {{ $order->order_id }}, status_id: {{ $status->status_id }}">
                        <i class="vp-dot" style="background: {{ $status->status_color ?: '#8a96b4' }}"></i>{{ $label($status->status_name) }}
                    </button>
                @endforeach
            </div>
        </div>
    @endunless
</div>

<div class="modal-footer vp-om-footer">
    <div class="vp-om-secondary">
        <a class="btn btn-light" href="{{ admin_url('orders/edit/'.$order->order_id) }}"><i class="fa fa-up-right-from-square"></i> @lang('igniter.voxpilot::orders.open_full')</a>
        <button type="button" class="btn btn-light" onclick="window.print()"><i class="fa fa-print"></i> @lang('igniter.voxpilot::orders.print')</button>
        @if($canceledId && !$isCanceled)
            <button type="button" class="btn btn-light vp-om-cancel"
                    data-request="onSetOrderStatus"
                    data-request-data="order_id: {{ $order->order_id }}, status_id: {{ $canceledId }}"
                    data-request-confirm="@lang('igniter.voxpilot::orders.cancel_confirm', ['id' => $order->order_id])">
                <i class="fa fa-ban"></i> @lang('igniter.voxpilot::orders.cancel')
            </button>
        @endif
    </div>
    @if($nextStatus && !$isCanceled)
        <button type="button" class="btn btn-primary vp-om-next"
                data-request="onSetOrderStatus"
                data-request-data="order_id: {{ $order->order_id }}, status_id: {{ $nextStatus->status_id }}">
            @lang('igniter.voxpilot::orders.move_to', ['status' => $label($nextStatus->status_name)]) <i class="fa fa-arrow-right"></i>
        </button>
    @endif
</div>
