subject = "{{ lang('igniter.voxpilot::mail.order_alert.subject', ['site_name' => $site_name]) }}"
==
{{ lang('igniter.voxpilot::mail.order_alert.l2') }}

{{ lang('igniter.voxpilot::mail.order_alert.l4', ['location_name' => $location_name]) }}

{{ lang('igniter.voxpilot::mail.order_alert.l6', ['order_number' => $order_number]) }}
{{ lang('igniter.voxpilot::mail.order_alert.l7', ['order_type' => $order_type]) }}

{{ lang('igniter.voxpilot::mail.order_alert.l9', ['first_name' => $first_name, 'last_name' => $last_name]) }}
{{ lang('igniter.voxpilot::mail.order_alert.l10', ['order_date' => $order_date]) }}
{{ lang('igniter.voxpilot::mail.order_alert.l11', ['order_type' => $order_type, 'order_time' => $order_time]) }}
{{ lang('igniter.voxpilot::mail.order_alert.l12', ['order_payment' => $order_payment]) }}

{{$order_address}}
{{ lang('igniter.voxpilot::mail.order_alert.l15', ['location_name' => $location_name]) }}

{{$order_comment}}

@if(!empty($order_menus))
    @foreach($order_menus as $order_menu)
        {{ $order_menu['menu_quantity'] }} x {{ $order_menu['menu_name'] }}
        {!! $order_menu['menu_options'] !!}
        - {{ $order_menu['menu_price'] }}
        - {{ $order_menu['menu_subtotal'] }}
        {!! $order_menu['menu_comment'] !!}
    @endforeach
@endif

@if(!empty($order_totals))
    @foreach($order_totals as $order_total)
        {{ $order_total['order_total_title'] }}
        {{ $order_total['order_total_value'] }}
    @endforeach
@endif
==
{{ lang('igniter.voxpilot::mail.order_alert.l36', ['order_type' => $order_type, 'order_number' => $order_number, 'location_name' => $location_name]) }}

{!! lang('igniter.voxpilot::mail.order_alert.l38', ['first_name' => e($first_name), 'last_name' => e($last_name)]) !!}
{!! lang('igniter.voxpilot::mail.order_alert.l39', ['order_date' => e($order_date)]) !!}
{!! lang('igniter.voxpilot::mail.order_alert.l40', ['order_type' => e($order_type), 'order_time' => e($order_time)]) !!}
{!! lang('igniter.voxpilot::mail.order_alert.l41', ['order_payment' => e($order_payment)]) !!}
{!! lang('igniter.voxpilot::mail.order_alert.l42', ['location_name' => e($location_name)]) !!}
{{ lang('igniter.voxpilot::mail.order_alert.l43', ['order_address' => $order_address]) }}

{{$order_comment}}

@partial('table')
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <thead>
    <tr>
        {!! lang('igniter.voxpilot::mail.order_alert.l51') !!}
        {!! lang('igniter.voxpilot::mail.order_alert.l52') !!}
        {!! lang('igniter.voxpilot::mail.order_alert.l53') !!}
    </tr>
    </thead>
    <tbody>
    @if(!empty($order_menus))
        @foreach($order_menus as $order_menu)
            <tr>
                <td>{{ $order_menu['menu_quantity'] }} x {{ $order_menu['menu_name'] }}<br>{!! $order_menu['menu_options'] !!}<br>{!! $order_menu['menu_comment'] !!}</td>
                <td align="right">{{ $order_menu['menu_price'] }}</td>
                <td align="right">{{ $order_menu['menu_subtotal'] }}</td>
            </tr>
        @endforeach
    @endif
    <tr>
        <td colspan="3">
            <hr>
        </td>
    </tr>
    @if(!empty($order_totals))
        @foreach($order_totals as $order_total)
            <tr>
                <td><br></td>
                <td align="right">{{ $order_total['order_total_title'] }}</td>
                <td align="right">{{ $order_total['order_total_value'] }}</td>
            </tr>
        @endforeach
    @endif
    </tbody>
</table>
@endpartial
