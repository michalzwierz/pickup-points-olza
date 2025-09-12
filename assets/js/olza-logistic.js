(function ($) {
    jQuery(document).ready(function () {
        jQuery(document).on('click', '.olza-load-map', function () {
            var selectedMethod = jQuery('input[name="shipping_method[0]"]:checked').val();
            var spedition = '';
            if (selectedMethod === 'olza_pickup_wedobox_28') {
                spedition = 'wedo-box';
            } else if (selectedMethod === 'olza_pickup_29') {
                spedition = 'ppl-ps';
            }
            var country = jQuery('#billing_country').val();
            var options = {
                api: {
                    url: olza_global.api_url,
                    accessToken: olza_global.access_token,
                    country: country ? country.toLowerCase() : '',
                    speditions: spedition ? [spedition] : [] ,
                    fields: olza_global.fields,
                    services: olza_global.services,
                    payments: olza_global.payments,
                    types: olza_global.types,
                    bounds: olza_global.bounds
                },
                callbacks: {
                    onSelect: function (item) {
                        var display = item.name || item.address || '';
                        jQuery('#olza_pickup_option').val(display);
                        jQuery('#delivery_point_id').val(item.id || '');
                        jQuery('#delivery_courier_id').val(item.spedition || '');
                        jQuery('.oloz-pickup-selection').show();
                        jQuery('.oloz-pickup-selection span').html(display);
                    }
                }
            };
            if (window.Olza && Olza.Widget) {
                Olza.Widget.pick(options);
            }
        });
    });
})(jQuery);
