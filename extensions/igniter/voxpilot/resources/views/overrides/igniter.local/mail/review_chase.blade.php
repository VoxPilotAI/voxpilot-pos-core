subject = "{{ lang('igniter.voxpilot::mail.review_chase.subject', ['site_name' => $site_name, 'order_number' => $order_number]) }}"
==
{{ lang('igniter.voxpilot::mail.review_chase.l2') }}

{{ lang('igniter.voxpilot::mail.review_chase.l4', ['first_name' => $first_name, 'last_name' => $last_name]) }}

{{ lang('igniter.voxpilot::mail.review_chase.l6') }}

{{ lang('igniter.voxpilot::mail.review_chase.l8') }}

{{ lang('igniter.voxpilot::mail.review_chase.l10') }}
{{$order_view_url}}

{{ lang('igniter.voxpilot::mail.review_chase.l13', ['order_number' => $order_number]) }}
{{ lang('igniter.voxpilot::mail.review_chase.l14', ['order_type' => $order_type]) }}

{{ lang('igniter.voxpilot::mail.review_chase.l16', ['order_date' => $order_date]) }}
{{ lang('igniter.voxpilot::mail.review_chase.l17', ['order_type' => $order_type, 'order_time' => $order_time]) }}

{{$order_address}}
{{ lang('igniter.voxpilot::mail.review_chase.l20', ['location_name' => $location_name]) }}

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
{{ lang('igniter.voxpilot::mail.review_chase.l42', ['first_name' => $first_name, 'last_name' => $last_name]) }}

{{ lang('igniter.voxpilot::mail.review_chase.l44') }}

{{ lang('igniter.voxpilot::mail.review_chase.l46') }}

{{ lang('igniter.voxpilot::mail.review_chase.l48', ['order_view_url' => $order_view_url]) }}

{!! lang('igniter.voxpilot::mail.review_chase.l50', ['order_type' => e($order_type), 'order_time' => e($order_time)]) !!}
{!! lang('igniter.voxpilot::mail.review_chase.l51', ['order_payment' => e($order_payment)]) !!}
{!! lang('igniter.voxpilot::mail.review_chase.l52', ['location_name' => e($location_name)]) !!}
{{ lang('igniter.voxpilot::mail.review_chase.l53', ['order_address' => $order_address]) }}

{{$order_comment}}

@partial('table')
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <thead>
    <tr>
        {!! lang('igniter.voxpilot::mail.review_chase.l61') !!}
        {!! lang('igniter.voxpilot::mail.review_chase.l62') !!}
        {!! lang('igniter.voxpilot::mail.review_chase.l63') !!}
    </tr>
    </thead>
    <tbody>
    @if(!empty($order_menus))
        @foreach($order_menus as $order_menu)
            <tr>
                <td>{{ $order_menu['menu_quantity'] }} x {{ $order_menu['menu_name'] }}
                    <br>{!! $order_menu['menu_options'] !!}<br>{!! $order_menu['menu_comment'] !!}</td>
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
