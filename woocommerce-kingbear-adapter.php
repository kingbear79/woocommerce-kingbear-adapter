<?php
/**
 * Plugin Name: WooCommerce KingBear Adapter
 * Description: Modulare Erweiterung für WooCommerce zur Versandabwicklung und Sendungsverfolgung über DHL.
 * Version: 0.1.4
 * Update URI: https://github.com/kingbear79/woocommerce-kingbear-adapter
 * Author: OpenAI
 * License: GPL2
 *
 * @package WooCommerce_KingBear_Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once __DIR__ . '/includes/class-kb-plugin.php';

// Boot the plugin.
KB_Plugin::instance();

// Activation & deactivation hooks.
register_activation_hook( __FILE__, array( 'KB_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KB_Plugin', 'deactivate' ) );
