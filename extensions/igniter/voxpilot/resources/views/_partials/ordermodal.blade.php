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
            @if($address)
                <div><dt>@lang('igniter.voxpilot::orders.address')</dt><dd>{{ $address }}</dd></div>
            @endif
            <div>
                <dt>@lang('igniter.voxpilot::orders.payment')</dt>
                <dd>
                    @if($order->processed)
                        <span class="vp-text-success"><i class="fa fa-circle-check"></i> @lang('igniter.voxpilot::orders.paid')</span>
                    @else
                        <span class="vp-text-danger">@lang('igniter.voxpilot::orders.unpaid')</span>
                    @endif
                    @if($order->payment_method) · {{ $order->payment_method->name }}@endif
                </dd>
            </div>
            @if($order->assignee)
                <div><dt>@lang('igniter.voxpilot::orders.driver')</dt><dd>{{ $order->assignee->name }}</dd></div>
            @endif
        </dl>
    </div>

    @if($order->telephone)
        <div class="vp-om-section">
            <div class="vp-om-section-title">@lang('igniter.voxpilot::orders.customer_history')</div>
            @if($history['count'])
                <p class="vp-om-history-sum">{{ trans_choice('igniter.voxpilot::orders.previous_orders', $history['count'], ['total' => currency_format($history['total'])]) }}</p>
                <ul class="vp-om-history">
                    @foreach($history['recent'] as $previous)
                        <li>
                            <button type="button" class="vp-card-open"
                                    data-request="onOpenOrder"
                                    data-request-data="order_id: {{ $previous->order_id }}">
                                <span class="vp-card-id">#{{ $previous->order_id }}</span>
                                <span class="vp-om-time">{{ $previous->created_at?->format('d M') }} · {{ $previous->menus->map(fn ($m) => $m->quantity.'× '.$m->name)->implode(', ') }}</span>
                            </button>
                            <span>{{ currency_format($previous->order_total) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="vp-om-history-sum"><span class="vp-pill vp-pill-primary"><i class="fa fa-star"></i> @lang('igniter.voxpilot::orders.first_order')</span></p>
            @endif
        </div>
    @endif

    @unless($isCanceled)
        <div class="vp-om-section">
            <div class="vp-om-section-title">@lang('igniter.voxpilot::orders.eta')</div>
            <div class="vp-om-inline">
                <div class="vp-segmented" role="group" aria-label="@lang('igniter.voxpilot::orders.eta')">
                    @foreach($etaSteps as $minutes)
                        <button type="button" data-request="onSetOrderEta" data-request-data="order_id: {{ $order->order_id }}, minutes: {{ $minutes }}">
                            {{ lang('igniter.voxpilot::orders.eta_minutes', ['min' => $minutes]) }}
                        </button>
                    @endforeach
                </div>
                @if($readyAt)
                    <span class="vp-pill vp-pill-primary"><i class="fa fa-stopwatch"></i> {{ lang('igniter.voxpilot::orders.eta_at', ['time' => $readyAt->format('H:i')]) }}</span>
                @endif
            </div>
            @if($phone)
                <p class="vp-om-time vp-om-help">@lang('igniter.voxpilot::orders.eta_help')</p>
            @endif
        </div>

        <div class="vp-om-tools">
            @unless($order->processed)
                <div class="vp-om-section">
                    <div class="vp-om-section-title">@lang('igniter.voxpilot::orders.mark_paid')</div>
                    <div class="vp-segmented" role="group" aria-label="@lang('igniter.voxpilot::orders.mark_paid')">
                        @foreach($paymentMethods as $method)
                            <button type="button"
                                    data-request="onMarkOrderPaid"
                                    data-request-data="order_id: {{ $order->order_id }}, method: '{{ $method }}'">
                                <i class="fa {{ ['cash' => 'fa-money-bill-wave', 'card' => 'fa-credit-card', 'transfer' => 'fa-building-columns'][$method] }}"></i>
                                @lang('igniter.voxpilot::orders.pay_'.$method)
                            </button>
                        @endforeach
                    </div>
                </div>
            @endunless
            @if($order->order_type === 'delivery')
                <div class="vp-om-section">
                    <div class="vp-om-section-title">@lang('igniter.voxpilot::orders.driver')</div>
                    <div class="vp-om-inline">
                        @if($staff->count())
                            <select class="form-select" name="assignee_id" aria-label="@lang('igniter.voxpilot::orders.assign_driver')"
                                    data-request="onAssignOrder"
                                    data-request-data="order_id: {{ $order->order_id }}">
                                <option value="0">@lang('igniter.voxpilot::orders.unassigned')</option>
                                @foreach($staff as $member)
                                    <option value="{{ $member->user_id }}" @selected((int) $order->assignee_id === (int) $member->user_id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        @if($mapUrl)
                            <a class="btn btn-light" href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer"><i class="fa fa-map-location-dot"></i> @lang('igniter.voxpilot::orders.open_map')</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endunless

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
