<?php
/**
 * Hauptklasse des KingBear Plugins.
 *
 * @package WooCommerce_KingBear_Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KB_Plugin {

    const VERSION = '0.1.0';

    /**
     * Singleton Instanz.
     *
     * @var KB_Plugin|null
     */
    protected static $instance = null;

    /**
     * Geladene Module.
     *
     * @var array
     */
    protected $modules = array();

    /**
     * Liefert die Plugin Instanz.
     *
     * @return KB_Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Initialisiert das Plugin.
     */
    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return; // Stoppe wenn WooCommerce nicht verfügbar ist.
        }

        require_once __DIR__ . '/class-kb-settings-page.php';
        $this->load_modules();

        add_filter( 'woocommerce_get_settings_pages', array( $this, 'register_settings_page' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
    }

    /**
     * Lädt alle Modulen.
     */
    protected function load_modules() {
        require_once __DIR__ . '/../modules/tracking/class-kb-tracking-module.php';
        $this->modules['tracking'] = new KB_Tracking_Module();
    }

    /**
     * Registriert Einstellungsseite.
     *
     * @param array $pages Vorhandene Seiten.
     * @return array
     */
    public function register_settings_page( $pages ) {
        $pages[] = new KB_Settings_Page( $this->modules );
        return $pages;
    }

    /**
     * Fügt die Admin-Seite zur Sendungsverfolgung hinzu.
     */
    public function register_admin_menu() {
        if ( isset( $this->modules['tracking'] ) ) {
            add_submenu_page(
                'woocommerce',
                __( 'Sendungsverfolgung', 'kb' ),
                __( 'Sendungsverfolgung', 'kb' ),
                'manage_woocommerce',
                'kb-tracking',
                array( $this->modules['tracking'], 'render_admin_page' )
            );
        }
    }

    /**
     * Plugin Aktivierung.
     */
    public static function activate() {
        KB_Tracking_Module::schedule_cron();
    }

    /**
     * Plugin Deaktivierung.
     */
    public static function deactivate() {
        KB_Tracking_Module::clear_cron();
    }
}
