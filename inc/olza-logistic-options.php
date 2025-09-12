<?php

/**
 * Adding Olza Shipping Setting Section
 */

add_filter('woocommerce_get_sections_shipping', 'add_custom_shipping_link', 10);

function add_custom_shipping_link($sections)
{

    $sections['olza_settings'] = __('Olza Logistic', 'olza-logistic-zoo');
    return $sections;
}

/**
 * Adding Shipping Setting Fields
 */

add_filter('woocommerce_get_settings_shipping', 'olza_logistic_get_settings', 10, 1);


function olza_logistic_get_settings($settings)
{
    global $current_section;

    if ($current_section == 'olza_settings') {

        $olza_options = get_option('olza_options');

        // Build country list from available data files.
        $country_options = array();
        foreach (glob(OLZA_LOGISTIC_PLUGIN_PATH . 'data/*.json') as $file) {
            if (preg_match('/^(\w{2})\.json$/', basename($file), $m)) {
                $code = strtoupper($m[1]);
                $country_options[$code] = $code;
            }
        }

        // Selected countries from saved options.
        $countries_selected = isset($olza_options['countries']) && is_array($olza_options['countries']) ? $olza_options['countries'] : array();

        // Build provider list based on selected country files.
        $provider_options = array();
        if (!empty($countries_selected)) {
            foreach ($countries_selected as $country_code) {
                $country_file = OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . strtolower($country_code) . '.json';
                if (file_exists($country_file)) {
                    $country_data = json_decode(file_get_contents($country_file));
                    if (!empty($country_data->data->speditions)) {
                        foreach ($country_data->data->speditions as $sped) {
                            $key = strtolower($country_code) . ':' . $sped->code;
                            $provider_options[$key] = strtoupper($country_code) . ' - ' . $sped->code;
                        }
                    }
                }
            }
        }

        $settings = array(

            array(
                'title' => __('Olza API Settings', 'olza-logistic-woo'),
                'type' => 'title',
                'desc' =>  __('Manage your API settings for the Pickup Points.', 'olza-logistic-woo'),
                'id' => 'olza_logistic_woocommerce_settings'
            ),
            array(
                'title' => __('API URL', 'olza-logistic-woo'),
                'type' => 'text',
                'desc' => __('Add your olza logistic API URL.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'id' => 'olza_options[api_url]',
                'css' => 'min-width:300px;',
            ),
            array(
                'title' => __('API Access Token', 'olza-logistic-woo'),
                'type' => 'text',
                'desc' => __('Add your olza logistic API access token.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'id' => 'olza_options[access_token]',
                'css' => 'min-width:300px;',
            ),
            array(
                'name' => __('Download Data', 'olza-logistic-woo'),
                'type' => 'button',
                'desc' => __('Click to download all available country data from API.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'class' => 'button-secondary',
                'id'    => 'olza-download',
            ),
            array(
                'title' => __('Available Countries', 'olza-logistic-woo'),
                'type'  => 'multicheck',
                'desc'  => __('Select countries to be available in store.', 'olza-logistic-woo'),
                'id'    => 'olza_options[countries]',
                'css'   => 'min-width:300px;',
                'options' => $country_options,
            ),
            array(
                'name' => __('Show Providers', 'olza-logistic-woo'),
                'type' => 'button',
                'desc' => __('Display providers for selected countries.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'class' => 'button-secondary',
                'id'    => 'olza-show-providers',
            ),
            array(
                'title' => __('Pickup Providers', 'olza-logistic-woo'),
                'type'  => 'multicheck',
                'desc'  => __('Select providers for chosen countries.', 'olza-logistic-woo'),
                'id'    => 'olza_options[providers]',
                'class' => 'olza-provider-field',
                'css'   => 'min-width:300px;',
                'options' => $provider_options,
            ),
            array(
                'title' => __('Fields', 'olza-logistic-woo'),
                'type'  => 'text',
                'desc'  => __('Comma-separated list of fields to request from API.', 'olza-logistic-woo'),
                'id'    => 'olza_options[fields]',
                'css'   => 'min-width:300px;',
                'default' => 'name,address,location',
            ),
            array(
                'title' => __('Services Filter', 'olza-logistic-woo'),
                'type'  => 'text',
                'desc'  => __('Comma-separated service codes.', 'olza-logistic-woo'),
                'id'    => 'olza_options[services]',
                'css'   => 'min-width:300px;',
            ),
            array(
                'title' => __('Payments Filter', 'olza-logistic-woo'),
                'type'  => 'text',
                'desc'  => __('Comma-separated payment methods.', 'olza-logistic-woo'),
                'id'    => 'olza_options[payments]',
                'css'   => 'min-width:300px;',
            ),
            array(
                'title' => __('Types Filter', 'olza-logistic-woo'),
                'type'  => 'text',
                'desc'  => __('Comma-separated pickup point types.', 'olza-logistic-woo'),
                'id'    => 'olza_options[types]',
                'css'   => 'min-width:300px;',
            ),
            array(
                'title' => __('Bounds', 'olza-logistic-woo'),
                'type'  => 'text',
                'desc'  => __('Bounding box for limiting results (minLon,minLat,maxLon,maxLat).', 'olza-logistic-woo'),
                'id'    => 'olza_options[bounds]',
                'css'   => 'min-width:300px;',
            ),
            array(
                'title' => __('Use batch full export', 'olza-logistic-woo'),
                'type'  => 'checkbox',
                'desc'  => __('Attempt to load pickup points using a single batch request.', 'olza-logistic-woo'),
                'id'    => 'olza_options[batch_full_export]',
            ),
            array(
                'name' => __('Update pick-up points', 'olza-logistic-woo'),
                'type' => 'button',
                'desc' => __('Download pickup point data for selected providers.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'class' => 'button-secondary',
                'id'    => 'olza-refresh',
            ),
            array(
                'title' => __('Map API key', 'olza-logistic-woo'),
                'type' => 'text',
                'desc' => __('Add your Mapbox API key.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'id' => 'olza_options[mapbox_api]',
                'css' => 'min-width:300px;',
            ),
            array(
                'name' => __('Set Pickup Pricing', 'olza-logistic-woo'),
                'type' => 'repeater',
                'desc' => __('Add fee according to the basket amount low to high.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'id'    => 'olza_options[basket_fee]',
                'key_val'    => 'basket_fee',

            ),
            array(
                'name' => __('Reset Data', 'olza-logistic-woo'),
                'type' => 'button',
                'desc' => __('Clear all downloaded country and pickup point data.', 'olza-logistic-woo'),
                'desc_tip' => true,
                'class' => 'button-secondary',
                'id'    => 'olza-reset-data',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'olza_logistic_woocommerce_settings'
            ),
        );
    }

    return $settings;
}