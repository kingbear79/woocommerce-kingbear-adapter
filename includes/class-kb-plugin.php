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
        add_action( 'woocommerce_loaded', array( $this, 'init' ) );
        add_action( 'admin_notices', array( $this, 'admin_notice_missing_wc' ) );
    }

    /**
     * Initialisiert das Plugin.
     */
    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return; // Stoppe wenn WooCommerce nicht verfügbar ist.
        }

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
        require_once __DIR__ . '/class-kb-settings-page.php';
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
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        require_once __DIR__ . '/../modules/tracking/class-kb-tracking-module.php';
        KB_Tracking_Module::schedule_cron();
        KB_Tracking_Module::create_tracking_for_existing_shipments();
    }

    /**
     * Plugin Deaktivierung.
     */
    public static function deactivate() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        require_once __DIR__ . '/../modules/tracking/class-kb-tracking-module.php';
        if ( 'yes' === get_option( 'kb_tracking_delete_data', 'no' ) ) {
            KB_Tracking_Module::delete_all_tracking();
        }
        KB_Tracking_Module::clear_cron();
    }

    /**
     * Zeigt einen Hinweis im Admin, wenn WooCommerce nicht aktiv ist.
     */
    public function admin_notice_missing_wc() {
        if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce KingBear Adapter erfordert ein aktiviertes WooCommerce.', 'kb' ) . '</p></div>';
    }
}
