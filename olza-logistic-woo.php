<?php

/**
 * Plugin Name: Olza Logistic WooCommerce
 * Description: Olza Logistic WooCommerce enables choosing pickup locations and delivery prices with configurable country and provider options on the checkout page.
 * Version:     1.1.0
 * Author:      Grand Brand
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: olza-logistic-woo
 *
 */
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

define('OLZA_LOGISTIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OLZA_LOGISTIC_PLUGIN_VERSION', '1.1.0');

define('OLZA_LOGISTIC_PLUGIN_DIR', plugin_dir_url(__DIR__));
define('OLZA_LOGISTIC_PLUGIN_PATH', plugin_dir_path(__FILE__));

/*
 * Function to load plugin textdomain
 */
function olza_logistic_load_textdomain()
{

	global $wp_version;

	/**
	 * Load text domain
	 */

	$olza_lang_dir = dirname(plugin_basename(__FILE__)) . '/languages/';
	$olza_lang_dir = apply_filters('olza_logistics_languages_directory', $olza_lang_dir);

	$get_locale = get_locale();

	if ($wp_version >= 4.7) {
		$get_locale = get_user_locale();
	}

	$locale = apply_filters('plugin_locale',  $get_locale, 'olza-logistic-woo');
	$mofile = sprintf('%1$s-%2$s.mo', 'olza-logistic-woo', $locale);

	$mofile_global  = WP_LANG_DIR . '/plugins/' . basename(OLZA_LOGISTIC_PLUGIN_PATH) . '/' . $mofile;

	if (file_exists($mofile_global)) {
		load_textdomain('olza-logistic-woo', $mofile_global);
	} else {
		load_plugin_textdomain('olza-logistic-woo', false, $olza_lang_dir);
	}

	/**
	 * Loading Files
	 */

	if (class_exists('WooCommerce')) {

		require OLZA_LOGISTIC_PLUGIN_PATH . '/inc/olza-logistic-functions.php';
		require OLZA_LOGISTIC_PLUGIN_PATH . '/inc/olza-logistic-options.php';
	}

	if (is_admin()) {
		require OLZA_LOGISTIC_PLUGIN_PATH . '/inc/olza-logistic-admin-functions.php';
	}
}

add_action('plugins_loaded', 'olza_logistic_load_textdomain');

/**
 * Check dependent plugin activation
 */

add_action('admin_init', 'olza_logistic_check_activation');

function olza_logistic_check_activation()
{

	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'olza_logistic_confirm_woocommerce_activate');
	}
}


// Admin notice if woocommerce not installed
function olza_logistic_confirm_woocommerce_activate()
{
	echo '<div class="notice notice-error">';
	echo __('<p> The <strong>Olza Logistic WooCommerce </strong> plugin requires <strong>WooCommerce</strong> plugin installed & activated. </p>', 'olza-logistic-woo');
	echo '</div>';
}

/**
 * Adding Scripts
 */


add_action('wp_enqueue_scripts', 'aloa_marketing_adding_scripts');

function aloa_marketing_adding_scripts()
{
        global $olza_options;
        $olza_options = get_option('olza_options');

        $api_url       = isset($olza_options['api_url']) ? $olza_options['api_url'] : '';
        $access_token  = isset($olza_options['access_token']) ? $olza_options['access_token'] : '';
        $fields        = isset($olza_options['fields']) ? array_map('trim', explode(',', $olza_options['fields'])) : array();
        $services      = isset($olza_options['services']) ? array_map('trim', explode(',', $olza_options['services'])) : array();
        $payments      = isset($olza_options['payments']) ? array_map('trim', explode(',', $olza_options['payments'])) : array();
        $types         = isset($olza_options['types']) ? array_map('trim', explode(',', $olza_options['types'])) : array();
        $bounds        = isset($olza_options['bounds']) ? $olza_options['bounds'] : '';

        if (class_exists('WooCommerce') && (is_checkout() || is_cart())) {
                $widget_url  = OLZA_LOGISTIC_PLUGIN_URL . 'node_modules/develart-olzalogistic-pickup-points-widget/dist/';
                $widget_path = OLZA_LOGISTIC_PLUGIN_PATH . 'node_modules/develart-olzalogistic-pickup-points-widget/dist/';

                wp_enqueue_script('olza-widget', $widget_url . 'olza-widget.js', array(), OLZA_LOGISTIC_PLUGIN_VERSION, true);

                foreach (glob($widget_path . '*.css') as $css_file) {
                        $handle = 'olza-widget-' . basename($css_file, '.css');
                        wp_enqueue_style($handle, $widget_url . basename($css_file), array(), OLZA_LOGISTIC_PLUGIN_VERSION);
                }
        }

        wp_enqueue_style('olza-logistic', OLZA_LOGISTIC_PLUGIN_URL . 'assets/css/olza-logistic.css');
        wp_enqueue_script('olza-logistic', OLZA_LOGISTIC_PLUGIN_URL . 'assets/js/olza-logistic.js', array('jquery', 'olza-widget'), OLZA_LOGISTIC_PLUGIN_VERSION, true);

        wp_localize_script('olza-logistic', 'olza_global', array(
                'api_url'      => $api_url,
                'access_token' => $access_token,
                'fields'       => $fields,
                'services'     => $services,
                'payments'     => $payments,
                'types'        => $types,
                'bounds'       => $bounds,
        ));
}


/**
 * Adding Admin Scripts
 */

add_action('admin_enqueue_scripts', 'aloa_marketing_adding_admin_scripts');

function aloa_marketing_adding_admin_scripts()
{

	wp_enqueue_script('olza-logistic-admin', OLZA_LOGISTIC_PLUGIN_URL . 'assets/js/olza-logistic-admin.js', array('jquery'), OLZA_LOGISTIC_PLUGIN_VERSION, true);
	wp_enqueue_script('olza-repeater', OLZA_LOGISTIC_PLUGIN_URL . 'assets/js/repeater.js', array('jquery'), OLZA_LOGISTIC_PLUGIN_VERSION, false);

	wp_localize_script('olza-logistic-admin', 'olza_global_admin', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('olza_load_files'),
		'confirm_msg' => __('Are you sure to refresh the data list! \n it takes around 1 minute to complete', 'olza-logistic-woo'),

	));
}