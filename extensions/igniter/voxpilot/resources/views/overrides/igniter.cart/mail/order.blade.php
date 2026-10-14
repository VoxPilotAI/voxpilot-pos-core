subject = "{{ lang('igniter.voxpilot::mail.order.subject', ['site_name' => $site_name, 'order_number' => $order_number]) }}"
==
{{ lang('igniter.voxpilot::mail.order.l2') }}

{{ lang('igniter.voxpilot::mail.order.l4', ['first_name' => $first_name, 'last_name' => $last_name]) }}

{{ lang('igniter.voxpilot::mail.order.l6') }}

{{ lang('igniter.voxpilot::mail.order.l8') }}
{{$order_view_url}}

{{ lang('igniter.voxpilot::mail.order.l11', ['order_number' => $order_number]) }}
{{ lang('igniter.voxpilot::mail.order.l12', ['order_type' => $order_type]) }}

{{ lang('igniter.voxpilot::mail.order.l14', ['order_date' => $order_date]) }}
{{ lang('igniter.voxpilot::mail.order.l15', ['order_type' => $order_type, 'order_time' => $order_time]) }}
{{ lang('igniter.voxpilot::mail.order.l16', ['order_payment' => $order_payment]) }}

{{$order_address}}
{{ lang('igniter.voxpilot::mail.order.l19', ['location_name' => $location_name]) }}

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
{{ lang('igniter.voxpilot::mail.order.l41', ['first_name' => $first_name, 'last_name' => $last_name]) }}

{{ lang('igniter.voxpilot::mail.order.l43') }}

{{ lang('igniter.voxpilot::mail.order.l45', ['order_type' => $order_type, 'order_number' => $order_number]) }}

{{ lang('igniter.voxpilot::mail.order.l47', ['order_view_url' => $order_view_url]) }}

{!! lang('igniter.voxpilot::mail.order.l49', ['order_type' => e($order_type), 'order_time' => e($order_time)]) !!}
{!! lang('igniter.voxpilot::mail.order.l50', ['order_payment' => e($order_payment)]) !!}
{!! lang('igniter.voxpilot::mail.order.l51', ['location_name' => e($location_name)]) !!}
{{ lang('igniter.voxpilot::mail.order.l52', ['order_address' => $order_address]) }}

{{$order_comment}}

@partial('table')
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <thead>
    <tr>
        {!! lang('igniter.voxpilot::mail.order.l60') !!}
        {!! lang('igniter.voxpilot::mail.order.l61') !!}
        {!! lang('igniter.voxpilot::mail.order.l62') !!}
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
        <td colspan="99">
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
