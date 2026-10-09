<div class="vp-ticket-actions" id="vp-ticket-actions-{{ $order->order_id }}">
    @if((int) $order->status_id === (int) setting('default_order_status', 1))
        <button type="button" class="btn btn-primary vp-ticket-accept"
                data-request="onSetOrderStatus"
                data-request-data="order_id: {{ $order->order_id }}, status_id: 'next'">
            <i class="fa fa-check"></i> @lang('igniter.voxpilot::orders.accept')
        </button>
    @endif
    <button type="button" class="btn btn-light"
            data-request="onOpenOrder"
            data-request-data="order_id: {{ $order->order_id }}"
            data-request-success="vpOpenOrderModal()">
        @lang('igniter.voxpilot::orders.details')
    </button>
</div>
