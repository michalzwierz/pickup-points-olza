(function ($) {

    /*
     * Responsible for admin custom js
     */

    jQuery(document).ready(function () {

        jQuery('.olzrepeater').repeaterolz({
            initEmpty: false,
            show: function () {
                jQuery(this).slideDown("slow", function () { });
            },
            hide: function (deleteElement) {
                jQuery(this).slideUp(deleteElement);
            }
        });


        // Hide provider field initially
        jQuery('.olza-provider-field').closest('tr').hide();

        // Download countries
        jQuery(document).on('click', '#olza-download', function (e) {
            e.preventDefault();
            var olz_obj = jQuery(this);
            jQuery('.olza-admin-spinner').show();
            olz_obj.prop('disabled', true);
            $.post(olza_global_admin.ajax_url, { nonce: olza_global_admin.nonce, action: 'olza_download_countries' }, function (response) {
                olz_obj.prop('disabled', false);
                jQuery('.olza-admin-spinner').hide();
                alert(response.message);
            }, 'json');
        });

        // Show providers based on selected countries
        jQuery(document).on('click', '#olza-show-providers', function (e) {
            e.preventDefault();
            var countries = [];
            jQuery('input[name="olza_options[countries][]"]:checked').each(function () {
                countries.push(jQuery(this).val());
            });
            if (countries.length === 0) {
                alert('Please select at least one country.');
                return false;
            }
            jQuery('.olza-admin-spinner').show();
            $.post(olza_global_admin.ajax_url, { nonce: olza_global_admin.nonce, action: 'olza_get_providers', countries: countries }, function (response) {
                jQuery('.olza-admin-spinner').hide();
                if (response.success) {
                    var row = jQuery('.olza-provider-field').closest('tr');
                    row.find('td').html(response.html);
                    row.show();
                } else {
                    alert(response.message);
                }
            }, 'json');
        });

        // Update pickup points
        jQuery(document).on('click', '#olza-refresh', function (e) {
            e.preventDefault();
            var countries = [], providers = [];
            jQuery('input[name="olza_options[countries][]"]:checked').each(function () {
                countries.push(jQuery(this).val());
            });
            jQuery('input[name="olza_options[providers][]"]:checked').each(function () {
                providers.push(jQuery(this).val());
            });
            if (countries.length === 0 || providers.length === 0) {
                alert('Please select countries and providers.');
                return false;
            }
            jQuery('.olza-admin-spinner').show();
            var olza_data = {
                nonce: olza_global_admin.nonce,
                action: 'olza_update_pickup_points',
                countries: countries,
                providers: providers
            };
            $.post(olza_global_admin.ajax_url, olza_data, function (response) {
                jQuery('.olza-admin-spinner').hide();
                alert(response.message);
            }, 'json');
        });

        // Reset data
        jQuery(document).on('click', '#olza-reset-data', function (e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete all downloaded data?')) {
                return false;
            }
            var btn = jQuery(this);
            jQuery('.olza-admin-spinner').show();
            btn.prop('disabled', true);
            $.post(olza_global_admin.ajax_url, { nonce: olza_global_admin.nonce, action: 'olza_reset_data' }, function (response) {
                jQuery('.olza-admin-spinner').hide();
                btn.prop('disabled', false);
                alert(response.message);
                if (response.success) {
                    location.reload();
                }
            }, 'json');
        });

    });

})(jQuery);