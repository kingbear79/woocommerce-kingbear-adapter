<?php
/**
 * Modul Sendungsverfolgung.
 *
 * @package WooCommerce_KingBear_Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-kb-tracking-data.php';

class KB_Tracking_Module {

    const CRON_HOOK = 'kb_track_shipments';

    public function __construct() {
        add_filter( 'woocommerce_data_stores', array( $this, 'register_data_store' ) );
        add_filter( 'cron_schedules', array( $this, 'register_cron_interval' ) );
        add_action( self::CRON_HOOK, array( $this, 'run_tracking' ) );

        // Platzhalter für Shiptastic Event.
        add_action( 'shiptastic_shipment_status_ready-for-shipping', array( $this, 'maybe_create_tracking' ) );
    }

    /**
     * Anzeigename des Moduls.
     */
    public function get_title() {
        return __( 'Sendungsverfolgung', 'kb' );
    }

    /**
     * Einstellungen für das Modul.
     */
    public function get_settings() {
        return array(
            array(
                'title' => __( 'DHL API Key', 'kb' ),
                'id'    => 'kb_tracking_dhl_api_key',
                'type'  => 'text',
            ),
            array(
                'title' => __( 'DHL API Secret', 'kb' ),
                'id'    => 'kb_tracking_dhl_api_secret',
                'type'  => 'text',
            ),
            array(
                'title' => __( 'DHL Benutzername', 'kb' ),
                'id'    => 'kb_tracking_dhl_username',
                'type'  => 'text',
            ),
            array(
                'title' => __( 'DHL Passwort', 'kb' ),
                'id'    => 'kb_tracking_dhl_password',
                'type'  => 'password',
            ),
            array(
                'title'   => __( 'Logging', 'kb' ),
                'id'      => 'kb_tracking_log_level',
                'type'    => 'select',
                'options' => array(
                    'error' => __( 'Nur Fehler', 'kb' ),
                    'debug' => __( 'Fehler und Debug', 'kb' ),
                ),
                'default' => 'error',
            ),
        );
    }

    /**
     * Registriert Cron Event.
     */
    public static function schedule_cron() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'kb_tracking_interval', self::CRON_HOOK );
        }
    }

    /**
     * Entfernt Cron Event.
     */
    public static function clear_cron() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Cron Intervall (30 Minuten).
     */
    public function register_cron_interval( $schedules ) {
        if ( ! isset( $schedules['kb_tracking_interval'] ) ) {
            $schedules['kb_tracking_interval'] = array(
                'interval' => 30 * 60,
                'display'  => __( 'Every 30 Minutes', 'kb' ),
            );
        }
        return $schedules;
    }

    /**
     * Registriert Datastore bei WooCommerce.
     */
    public function register_data_store( $stores ) {
        $stores['kb_tracking'] = 'KB_Tracking_Data_Store';
        return $stores;
    }

    /**
     * Erstellt KB_Tracking Objekte bei Shiptastic Events.
     *
     * @param object $shipment Shiptastic Shipment Objekt.
     */
    public function maybe_create_tracking( $shipment ) {
        if ( empty( $shipment ) || empty( $shipment->id ) ) {
            return;
        }
        $tracking = new KB_Tracking( $shipment->id );
        if ( $tracking->get_tracking_id() ) {
            return;
        }
        $tracking->set_tracking_id( isset( $shipment->tracking_id ) ? $shipment->tracking_id : '' );
        $tracking->save();
    }

    /**
     * Cron Callback.
     */
    public function run_tracking() {
        $ids = get_option( KB_Tracking_Data_Store::IDS_OPTION, array() );
        if ( empty( $ids ) ) {
            return;
        }

        $api_key    = get_option( 'kb_tracking_dhl_api_key' );
        $api_secret = get_option( 'kb_tracking_dhl_api_secret' );
        $username   = get_option( 'kb_tracking_dhl_username' );
        $password   = get_option( 'kb_tracking_dhl_password' );
        if ( ! $api_key || ! $api_secret || ! $username || ! $password ) {
            return;
        }

        $logger   = wc_get_logger();
        $log_args = array( 'source' => 'kb-tracking' );
        $debug    = 'debug' === get_option( 'kb_tracking_log_level', 'error' );

        $chunks = array_chunk( $ids, 15 );
        foreach ( $chunks as $chunk ) {
            $piece_codes = implode( ';', array_map( 'sanitize_text_field', $chunk ) );
            $url         = add_query_arg(
                array(
                    'request'   => 'd-get-piece-detail',
                    'piececode' => $piece_codes,
                ),
                'https://api-eu.dhl.com/track/shipments'
            );

            $response = wp_remote_get(
                $url,
                array(
                    'headers' => array(
                        'dhl-api-key'    => $api_key,
                        'dhl-api-secret' => $api_secret,
                    ),
                    'timeout' => 30,
                    'auth'    => array( $username, $password ),
                )
            );

            if ( is_wp_error( $response ) ) {
                $logger->error( 'DHL API Fehler: ' . $response->get_error_message(), $log_args );
                continue;
            }

            $body = wp_remote_retrieve_body( $response );
            $xml  = simplexml_load_string( $body );

            if ( ! $xml ) {
                $logger->error( 'Ungültige Antwort von der DHL API', $log_args );
                continue;
            }

            $data = json_decode( wp_json_encode( $xml ), true );
            if ( empty( $data['data'] ) ) {
                continue;
            }

            $shipments = $data['data'];
            if ( isset( $shipments['@attributes'] ) ) {
                $shipments = array( $shipments );
            }

            foreach ( $shipments as $shipment ) {
                if ( ! isset( $shipment['@attributes']['piece-code'] ) ) {
                    continue;
                }
                $piece    = $shipment['@attributes'];
                $tracking = new KB_Tracking( $piece['piece-code'] );
                if ( ! $tracking->get_do_tracking() ) {
                    continue;
                }
                $tracking->set_tracking_id( $piece['piece-code'] );
                $tracking->set_status( $piece['status'] ?? '' );
                $tracking->set_status_timestamp( $piece['status-timestamp'] ?? '' );
                $tracking->set_delivered( isset( $piece['delivery-event-flag'] ) && '1' === (string) $piece['delivery-event-flag'] );
                $tracking->set_is_return( isset( $piece['ruecksendung'] ) && 'true' === (string) $piece['ruecksendung'] );
                $tracking->set_raw_data( $piece );
                if ( isset( $shipment['data'] ) ) {
                    $tracking->set_events( $shipment['data'] );
                }
                $tracking->save();
                if ( $debug ) {
                    $logger->debug( 'Tracking aktualisiert: ' . $tracking->get_tracking_id(), $log_args );
                }
                if ( $tracking->get_delivered() ) {
                    // TODO: Status des Shiptastic Shipments auf "shipped" setzen.
                }
            }
        }
    }

    /**
     * Admin Seite für Tracking Objekte.
     */
    public function render_admin_page() {
        $ids   = get_option( KB_Tracking_Data_Store::IDS_OPTION, array() );
        $items = array();
        foreach ( $ids as $id ) {
            $items[] = new KB_Tracking( $id );
        }

        usort(
            $items,
            function ( $a, $b ) {
                return strtotime( $b->get_status_timestamp() ) - strtotime( $a->get_status_timestamp() );
            }
        );

        echo '<div class="wrap"><h1>' . esc_html__( 'Sendungsverfolgung', 'kb' ) . '</h1>';
        echo '<table class="widefat"><thead><tr>';
        echo '<th>' . esc_html__( 'Zeitstempel', 'kb' ) . '</th>';
        echo '<th>' . esc_html__( 'Tracking-ID', 'kb' ) . '</th>';
        echo '<th>' . esc_html__( 'Empfänger', 'kb' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'kb' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $items as $item ) {
            $raw = $item->get_raw_data();

            $recipient = implode(
                ', ',
                array_filter(
                    array(
                        isset( $raw['pan-recipient-name'] ) ? $raw['pan-recipient-name'] : '',
                        isset( $raw['pan-recipient-address'] ) ? $raw['pan-recipient-address'] : ''
                    )
                )
            );

            echo '<tr>';
            echo '<td>' . esc_html( $item->get_status_timestamp() ) . '</td>';
            echo '<td>' . esc_html( $item->get_tracking_id() ) . '</td>';
            echo '<td>' . esc_html( $recipient ) . '</td>';
            echo '<td>' . esc_html( $item->get_status() ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
