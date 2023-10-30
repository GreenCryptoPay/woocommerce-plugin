<?php

/**
 * Plugin Name: Green Crypto Processing
 * Description: Green Crypto Processing - payment gateway for WooCommerce.
 * Version:     1.0.0
 * Plugin URI:  https://github.com/GreenCryptoPay
 * Author URI:  https://github.com/GreenCryptoPay
 * Author:      GreenCryptoPay
 * License:     MIT
 * Text Domain: GreenCryptoPay
 * Domain Path: /languages
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

require_once 'vendor/autoload.php';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'GREENCRYPTOPAY_VERSION', '1.0.0' );

/**
 * Currently plugin URL.
 */
define( 'GREENCRYPTOPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-greencryptopay-activator.php
 */
function activate_GreenCryptoPay() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-greencryptopay-activator.php';
    GreenCryptoPay_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-greencryptopay-deactivator.php
 */
function deactivate_GreenCryptoPay() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-greencryptopay-deactivator.php';
    GreenCryptoPay_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_GreenCryptoPay' );
register_deactivation_hook( __FILE__, 'deactivate_GreenCryptoPay' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-greencryptopay.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */

function run_GreenCryptoPay() {
    $plugin = new GreenCryptoPay();
    $plugin->run();
}

run_GreenCryptoPay();

add_action('init', 'add_payment_page');
function add_payment_page() {
    add_rewrite_endpoint( 'greencryptopay/payment', EP_ROOT);
    flush_rewrite_rules();
}

add_filter( 'posts_clauses_request', function ($pieces, $wp_query) {
    if( isset( $wp_query->query['greencryptopay/payment'] ) && $wp_query->is_main_query() ){
        $pieces['where'] = ' AND ID = 0';
    }
    return $pieces;
}, 10, 2 );

add_action('template_include', function ($template) {
    global $wp_query;
    if (isset($wp_query->query['greencryptopay/payment'])) {
        if (empty($wp_query->query['greencryptopay/payment'])) {
            $template = __DIR__ . '/payment.php';
        } else {
            $wp_query->set_404();
            status_header( 404 );
            get_template_part( 404 );
            $template = get_404_template();
        }
    }
    return $template;
}, 20);




