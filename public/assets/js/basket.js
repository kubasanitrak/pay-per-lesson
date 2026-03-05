jQuery(document).ready(function($) {
    $('.ppl-add-to-basket').on('click', function() {
        $.post(ppl_ajax.ajax_url, {
            action: 'ppl_add_to_basket',
            id: $(this).data('id'),
            nonce: ppl_ajax.nonce
        }, function(res) {
            alert('Added to basket! Items: ' + res.data.count);
        });
    });
});