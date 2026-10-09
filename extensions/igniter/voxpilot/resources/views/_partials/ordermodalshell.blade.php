{{-- Order quick-actions modal shell; content comes from onOpenOrder / onSetOrderStatus. --}}
<div class="modal fade" id="vp-order-modal" tabindex="-1" aria-labelledby="vp-order-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content" id="vp-order-modal-content">
            <div class="modal-body vp-om-loading"><span class="spinner-border spinner-border-sm"></span></div>
        </div>
    </div>
</div>
<script>
    window.vpOpenOrderModal = function () {
        var el = document.getElementById('vp-order-modal');
        if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el).show();
    };
</script>
