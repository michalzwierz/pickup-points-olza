<?php
/**
 * Admin ajax handlers for Olza plugin.
 */

add_action('wp_ajax_olza_download_countries', 'olza_download_countries_callback');
add_action('wp_ajax_olza_get_providers', 'olza_get_providers_callback');
add_action('wp_ajax_olza_update_pickup_points', 'olza_update_pickup_points_callback');
add_action('wp_ajax_olza_reset_data', 'olza_reset_data_callback');
add_action('admin_notices', 'olza_countries_import_notice');

function olza_countries_import_notice() {
    $message = get_transient('olza_countries_import_notice');
    if ($message) {
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        delete_transient('olza_countries_import_notice');
    }
}

function olza_download_countries_callback() {
    global $olza_options;
    $olza_options = get_option('olza_options');

    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'olza_load_files')) {
        $api_url = isset($olza_options['api_url']) ? $olza_options['api_url'] : '';
        $access_token = isset($olza_options['access_token']) ? $olza_options['access_token'] : '';

        if (empty($api_url) || empty($access_token)) {
            echo json_encode(array('success' => false, 'message' => __('Please verify APP URL & Access Token.', 'olza-logistic-woo')));
            wp_die();
        }

        $args = array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json')
        );

        $countries_endpoint = olza_validate_url($api_url . '/countries');
        $countries_url = add_query_arg(array('access_token' => $access_token), $countries_endpoint);
        $countries_response = wp_remote_get($countries_url, $args);

        if (is_wp_error($countries_response)) {
            $error_message = $countries_response->get_error_message();
            echo json_encode(array('success' => false, 'message' => $error_message));
            wp_die();
        }

        $countries_body = json_decode(wp_remote_retrieve_body($countries_response), true);
        $country_arr = array();

        if (is_array($countries_body) && !empty($countries_body['data'])) {
            $data = $countries_body['data'];

            // Some APIs return countries under a nested key.
            if (isset($data['countries'])) {
                $data = $data['countries'];
            }

            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_string($key) && preg_match('/^[a-z]{2}$/i', $key)) {
                        $country_arr[] = strtolower($key);
                    } elseif (is_string($value)) {
                        $country_arr[] = strtolower($value);
                    } elseif (is_array($value) && isset($value['code'])) {
                        $country_arr[] = strtolower($value['code']);
                    }
                }
            }
        }

        if (empty($country_arr)) {
            $notice = __('No countries were imported. The API response was empty or malformed.', 'olza-logistic-woo');
            set_transient('olza_countries_import_notice', $notice, 30);
            echo json_encode(array('success' => false, 'message' => $notice));
            wp_die();
        }

        $config_endpoint = olza_validate_url($api_url . '/config');
        foreach ($country_arr as $country) {
            $config_url = add_query_arg(array('access_token' => $access_token, 'country' => $country), $config_endpoint);
            $config_response = wp_remote_get($config_url, $args);
            if (!is_wp_error($config_response)) {
                $body = wp_remote_retrieve_body($config_response);
                file_put_contents(OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . $country . '.json', $body);
            }
        }

        echo json_encode(array('success' => true, 'message' => __('Countries Added Successfully', 'olza-logistic-woo')));
        wp_die();
    } else {
        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }
}

function olza_get_providers_callback() {
    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'olza_load_files')) {
        $countries = isset($_POST['countries']) && is_array($_POST['countries']) ? array_map('strtolower', $_POST['countries']) : array();
        $html = '';

        foreach ($countries as $country) {
            $file_path = OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . $country . '.json';
            if (file_exists($file_path)) {
                $country_data = json_decode(file_get_contents($file_path));
                if (!empty($country_data->data->speditions)) {
                    foreach ($country_data->data->speditions as $sped) {
                        $value = $country . ':' . $sped->code;
                        $label = strtoupper($country) . ' - ' . $sped->code;
                        $html .= '<label><input type="checkbox" name="olza_options[providers][]" value="' . esc_attr($value) . '"> ' . esc_html($label) . '</label><br />';
                    }
                }
            }
        }

        echo json_encode(array('success' => true, 'html' => $html));
        wp_die();
    } else {
        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }
}

function olza_update_pickup_points_callback() {
    global $olza_options;
    $olza_options = get_option('olza_options');

    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'olza_load_files')) {
        $api_url = isset($olza_options['api_url']) ? $olza_options['api_url'] : '';
        $access_token = isset($olza_options['access_token']) ? $olza_options['access_token'] : '';

        if (empty($api_url) || empty($access_token)) {
            echo json_encode(array('success' => false, 'message' => __('Please verify APP URL & Access Token.', 'olza-logistic-woo')));
            wp_die();
        }

        $countries = isset($_POST['countries']) && is_array($_POST['countries']) ? array_map('strtolower', $_POST['countries']) : array();
        $providers = isset($_POST['providers']) && is_array($_POST['providers']) ? $_POST['providers'] : array();
        $message = __('Files Not Updated', 'olza-logistic-woo');

        $find_endpoint = olza_validate_url($api_url . '/find');
        $args = array(
            'timeout' => 300,
            'headers' => array('Content-Type' => 'application/json')
        );

        foreach ($countries as $country) {
            $all_items = array();
            foreach ($providers as $prov) {
                list($prov_country, $sped_value) = array_pad(explode(':', $prov), 2, '');
                if ($prov_country !== $country) {
                    continue;
                }
                $find_url = add_query_arg(array('access_token' => $access_token, 'country' => $country, 'spedition' => $sped_value), $find_endpoint);
                $find_response = wp_remote_get($find_url, $args);
                if (!is_wp_error($find_response)) {
                    $find_data = wp_remote_retrieve_body($find_response);
                    $file_path = OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . $country . '_' . $sped_value . '.json';
                    file_put_contents($file_path, $find_data);
                    $decoded = json_decode($find_data);
                    if (!empty($decoded->data->items)) {
                        $all_items = array_merge($all_items, $decoded->data->items);
                    }
                    $message .= ' ' . strtoupper($country) . ' ' . $sped_value . ' added\n';
                }
            }
            if (!empty($all_items)) {
                $all_data = array('success' => true, 'code' => 0, 'message' => 'OK', 'data' => array('items' => $all_items));
                file_put_contents(OLZA_LOGISTIC_PLUGIN_PATH . 'data/' . $country . '_all.json', json_encode($all_data));
            }
        }

        echo json_encode(array('success' => true, 'message' => $message));
        wp_die();
    } else {
        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }
}

function olza_reset_data_callback() {
    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'olza_load_files')) {
        foreach (glob(OLZA_LOGISTIC_PLUGIN_PATH . 'data/*.json') as $file) {
            @unlink($file);
        }
        echo json_encode(array('success' => true, 'message' => __('Data reset successfully', 'olza-logistic-woo')));
        wp_die();
    } else {
        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }
}

/**
 * APP Url Validation
 */
if (!function_exists('olza_validate_url')) {
    function olza_validate_url($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            return false;
        }

        if (strpos($url, '://') !== false) {
            list($protocol, $rest_of_url) = explode('://', $url, 2);
            $rest_of_url = str_replace('//', '/', $rest_of_url);
            return $protocol . '://' . $rest_of_url;
        } else {
            return str_replace('//', '/', $url);
        }
    }
}

/**
 * Add woo button field
 */
add_action('woocommerce_admin_field_button', 'olza_woo_add_admin_field_button');
function olza_woo_add_admin_field_button($value)
{
    $option_value = (array) WC_Admin_Settings::get_option($value['id']);
    $description = WC_Admin_Settings::get_field_description($value);
?>
    <style>
        .olza-admin-spinner {
            display: none;
        }
    </style>
    <tr valign="top">
        <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?></label>
            <?php echo $description['tooltip_html']; ?>
        </th>
        <td class="olza-table olza-table-<?php echo sanitize_title($value['type']) ?>">
            <input name="<?php echo esc_attr($value['name']); ?>" id="<?php echo esc_attr($value['id']); ?>" type="submit" style="<?php echo esc_attr($value['css']); ?>" value="<?php echo esc_attr($value['name']); ?>" class="<?php echo esc_attr($value['class']); ?>" />
            <?php echo $description['description']; ?>
            <span class="olza-admin-spinner"><img src="<?php echo OLZA_LOGISTIC_PLUGIN_URL . 'assets/images/spinner.gif'; ?>" alt="<?php echo __('Spinner', 'olza-logistic-woo'); ?>" /></span>
        </td>
    </tr>
<?php
}

/**
 * Add multi checkbox field
 */
add_action('woocommerce_admin_field_multicheck', 'olza_woo_add_admin_field_multicheck');
function olza_woo_add_admin_field_multicheck($field)
{
    $option_value = (array) WC_Admin_Settings::get_option($field['id']);
    $description = WC_Admin_Settings::get_field_description($field);
?>
    <tr valign="top" class="<?php echo esc_attr(isset($field['class']) ? $field['class'] : ''); ?>">
        <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr($field['id']); ?>"><?php echo esc_html($field['title']); ?></label>
            <?php echo $description['tooltip_html']; ?>
        </th>
        <td class="olza-table olza-table-<?php echo sanitize_title($field['type']) ?>">
            <?php
            if (!empty($field['options'])) {
                foreach ($field['options'] as $key => $label) {
                    $checked = in_array($key, $option_value) ? 'checked="checked"' : '';
                    echo '<label><input type="checkbox" name="' . esc_attr($field['id']) . '[]" value="' . esc_attr($key) . '" ' . $checked . ' /> ' . esc_html($label) . '</label><br />';
                }
            } else {
                echo '<em>' . __('No options available', 'olza-logistic-woo') . '</em>';
            }
            ?>
        </td>
    </tr>
<?php
}

/**
 * Add repeater field
 */
add_action('woocommerce_admin_field_repeater', 'olza_woo_add_admin_field_repeater');
function olza_woo_add_admin_field_repeater($field)
{
    $option_value = (array) WC_Admin_Settings::get_option($field['id']);
    $description = WC_Admin_Settings::get_field_description($field);
    $olza_options = get_option('olza_options');
?>
    <style>
        .olza-rep-sett input[type="number"] {
            width: 20% !important;
            min-height: 30px !important;
        }
        .olza-rep-sett select {
            width: 30% !important;
        }
        .olza-rep-item {
            margin: 10px 0;
        }
    </style>
    <tr valign="top" class="olza-rep-sett">
        <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr($field['id']); ?>"><?php echo esc_html($field['title']); ?></label>
            <?php echo $description['tooltip_html']; ?>
        </th>
        <td class="olza-table olza-table-<?php echo sanitize_title($field['type']) ?>">
            <?php
            if (isset($olza_options[$field['key_val']]) && !empty($olza_options[$field['key_val']]) && is_array($olza_options[$field['key_val']])) {
            ?>
                <div class="olzrepeater">
                    <div data-repeater-list="<?php echo esc_attr($field['id']); ?>">
                        <?php
                        foreach ($olza_options[$field['key_val']] as $key => $backet_data) {
                            $cond_val = isset($backet_data['condition']) ? $backet_data['condition'] : '';
                        ?>
                            <div data-repeater-item class="olza-rep-item">
                                <input type="number" placeholder="<?php echo __('Basket Amount', 'olza-logistic-woo'); ?>" name="<?php echo esc_attr($field['id']); ?>[<?php echo $key; ?>][amount]" value="<?php echo isset($backet_data['amount']) ? $backet_data['amount'] : ''; ?>" />
                                <select name="<?php echo esc_attr($field['id']); ?>[<?php echo $key; ?>][condition]">
                                    <option value="equal" <?php selected($cond_val, 'equal', true); ?>><?php echo __('Equal', 'olza-logistic-woo'); ?></option>
                                    <option value="less" <?php selected($cond_val, 'less', true); ?>><?php echo __('Less', 'olza-logistic-woo'); ?></option>
                                    <option value="less_than_equal" <?php selected($cond_val, 'less_than_equal', true); ?>><?php echo __('Less than Equal', 'olza-logistic-woo'); ?></option>
                                    <option value="greater" <?php selected($cond_val, 'greater', true); ?>><?php echo __('Greater', 'olza-logistic-woo'); ?></option>
                                    <option value="greater_than_equal" <?php selected($cond_val, 'greater_than_equal', true); ?>><?php echo __('Greater than Equal', 'olza-logistic-woo'); ?></option>
                                </select>
                                <input type="number" placeholder="<?php echo __('Fee', 'olza-logistic-woo'); ?>" name="<?php echo esc_attr($field['id']); ?>[<?php echo $key; ?>][fee]" value="<?php echo isset($backet_data['fee']) ? $backet_data['fee'] : ''; ?>" />
                                <input data-repeater-delete type="button" value="<?php echo __('Delete', 'olza-logistic-woo'); ?>" class="button-secondary" />
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <input data-repeater-create type="button" value="<?php echo __('Add', 'olza-logistic-woo'); ?>" class="button-secondary" />
                </div>
            <?php
            } else {
            ?>
                <div class="olzrepeater">
                    <div data-repeater-list="<?php echo esc_attr($field['id']); ?>">
                        <div data-repeater-item>
                            <input type="number" name="amount" value="" placeholder="<?php echo __('Amount', 'olza-logistic-woo'); ?>" />
                            <select name="condition">
                                <option value="equal"><?php echo __('Equal', 'olza-logistic-woo'); ?></option>
                                <option value="less"><?php echo __('Less', 'olza-logistic-woo'); ?></option>
                                <option value="less_than_equal"><?php echo __('Less than Equal', 'olza-logistic-woo'); ?></option>
                                <option value="greater"><?php echo __('Greater', 'olza-logistic-woo'); ?></option>
                                <option value="greater_than_equal"><?php echo __('Greater than Equal', 'olza-logistic-woo'); ?></option>
                            </select>
                            <input type="number" name="fee" value="" placeholder="<?php echo __('Fee', 'olza-logistic-woo'); ?>" />
                            <input data-repeater-delete type="button" value="Delete" />
                        </div>
                    </div>
                    <input data-repeater-create type="button" value="Add" />
                </div>

            <?php
            }
            ?>
        </td>
    </tr>
<?php
}
