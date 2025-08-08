<?php
/**
 * WooCommerce Einstellungs-Seite für das Plugin.
 *
 * @package WooCommerce_KingBear_Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Settings_Page', false ) ) {
    return;
}

class KB_Settings_Page extends WC_Settings_Page {

    /**
     * Module des Plugins.
     *
     * @var array
     */
    protected $modules = array();

    /**
     * Konstruktor.
     *
     * @param array $modules Geladene Module.
     */
    public function __construct( $modules ) {
        $this->id      = 'kb_adapter';
        $this->label   = __( 'KingBear Adapter', 'kb' );
        $this->modules = $modules;
        parent::__construct();
    }

    /**
     * Liefert die Einstellungen.
     *
     * @return array
     */
    public function get_settings() {
        $settings = array();

        foreach ( $this->modules as $id => $module ) {
            if ( ! method_exists( $module, 'get_settings' ) ) {
                continue;
            }

            $settings[] = array(
                'title' => $module->get_title(),
                'type'  => 'title',
                'id'    => 'kb_' . $id . '_options',
            );

            $settings = array_merge( $settings, $module->get_settings() );

            $settings[] = array(
                'type' => 'sectionend',
                'id'   => 'kb_' . $id . '_options',
            );
        }

        return apply_filters( 'kb_adapter_settings', $settings );
    }
}
