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

    const CRON_HOOK     = 'kb_track_shipments';
    const NOTICE_OPTION = 'kb_tracking_admin_notice';

    public function __construct() {
        add_filter( 'woocommerce_data_stores', array( $this, 'register_data_store' ) );
        add_filter( 'cron_schedules', array( $this, 'register_cron_interval' ) );
        add_action( self::CRON_HOOK, array( $this, 'run_tracking' ) );

        // Platzhalter für Shiptastic Event.
        add_action( 'shiptastic_shipment_status_ready-for-shipping', array( $this, 'maybe_create_tracking' ) );

        add_action( 'admin_notices', array( $this, 'show_admin_notice' ) );
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
            array(
                'title'   => __( 'Tracking-Daten bei Deaktivierung entfernen', 'kb' ),
                'id'      => 'kb_tracking_delete_data',
                'type'    => 'checkbox',
                'desc'    => __( 'Entfernt alle gespeicherten Tracking-Daten beim Deaktivieren des Plugins.', 'kb' ),
                'default' => 'no',
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
     * Erstellt Tracking-Objekte für vorhandene Shipments.
     */
    public static function create_tracking_for_existing_shipments() {
        // Suche nach Shipments mit dem Status "ready-for-shipping".
        $shipments = function_exists( 'wc_stc_get_shipments' ) ? wc_stc_get_shipments(
            array(
                'status' => array( 'ready-for-shipping' ),
                'limit'  => -1,
            )
        ) : array();

        if ( empty( $shipments ) ) {
            return;
        }

        $module = new self();
        foreach ( $shipments as $shipment ) {
            $module->maybe_create_tracking( $shipment );
        }
    }

    /**
     * Entfernt alle gespeicherten Tracking-Objekte.
     */
    public static function delete_all_tracking() {
        $ids = get_option( KB_Tracking_Data_Store::IDS_OPTION, array() );
        if ( empty( $ids ) ) {
            return;
        }
        foreach ( $ids as $id ) {
            delete_option( 'kb_tracking_' . $id );
        }
        delete_option( KB_Tracking_Data_Store::IDS_OPTION );
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
     * @param mixed $shipment Shiptastic Shipment Objekt oder ID.
     */
    public function maybe_create_tracking( $shipment ) {
        if ( function_exists( 'wc_stc_get_shipment' ) ) {
            $shipment = wc_stc_get_shipment( $shipment );
        }

        if ( ! $shipment ) {
            return;
        }

        $tracking = new KB_Tracking( $shipment->get_id() );
        if ( $tracking->get_tracking_id() ) {
            return;
        }

        $tracking->set_tracking_id( $shipment->get_tracking_id() );
        $tracking->save();
    }

    /**
     * Cron Callback.
     */
    public function run_tracking() {
        $logger   = wc_get_logger();
        $log_args = array( 'source' => 'kb-tracking' );
        $debug    = 'debug' === get_option( 'kb_tracking_log_level', 'error' );

        $logger->info( 'Tracking update started', $log_args );

        $api_key    = get_option( 'kb_tracking_dhl_api_key' );
        $api_secret = get_option( 'kb_tracking_dhl_api_secret' );
        $username   = get_option( 'kb_tracking_dhl_username' );
        $password   = get_option( 'kb_tracking_dhl_password' );
        if ( ! $api_key || ! $api_secret || ! $username || ! $password ) {
            $logger->warning( 'Tracking update aborted: missing credentials', $log_args );
            update_option( self::NOTICE_OPTION, __( 'Sendungsverfolgung konnte nicht aktualisiert werden. Bitte Zugangsdaten hinterlegen.', 'kb' ) );
            return false;
        }

        // Füge alle Shipments mit Status "shipped" zur Tracking-Liste hinzu.
        $shipments = function_exists( 'wc_stc_get_shipments' ) ? wc_stc_get_shipments(
            array(
                'status' => array( 'shipped' ),
                'limit'  => -1,
            )
        ) : array();

        foreach ( $shipments as $shipment ) {
            if ( ! $shipment->get_tracking_id() ) {
                continue;
            }
            $this->maybe_create_tracking( $shipment );
        }

        $ids = get_option( KB_Tracking_Data_Store::IDS_OPTION, array() );
        if ( empty( $ids ) ) {
            $logger->info( 'Tracking update finished: no tracking IDs', $log_args );
            delete_option( self::NOTICE_OPTION );
            return true;
        }

        $notice = '';
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

            if ( $debug ) {
                $logger->debug( 'DHL API request: ' . $url, $log_args );
            }

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
                if ( $debug ) {
                    $logger->debug( 'DHL API error response: ' . print_r( $response, true ), $log_args );
                }
                $logger->error( 'DHL API Fehler: ' . $response->get_error_message(), $log_args );
                $notice = sprintf( __( 'Fehler beim Abrufen der DHL API: %s', 'kb' ), $response->get_error_message() );
                continue;
            }

            $body = wp_remote_retrieve_body( $response );

            if ( $debug ) {
                $logger->debug( 'DHL API response: ' . $body, $log_args );
            }

            $xml = simplexml_load_string( $body );

            if ( ! $xml ) {
                $logger->error( 'Ungültige Antwort von der DHL API', $log_args );
                $notice = __( 'Ungültige Antwort von der DHL API.', 'kb' );
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

        if ( $notice ) {
            update_option( self::NOTICE_OPTION, $notice );
        } else {
            delete_option( self::NOTICE_OPTION );
        }
        $logger->info( 'Tracking update finished', $log_args );
        return true;
    }

    /**
     * Zeigt Fehlerhinweise im Adminbereich.
     */
    public function show_admin_notice() {
        $notice = get_option( self::NOTICE_OPTION );
        if ( ! $notice ) {
            return;
        }
        $url = admin_url( 'admin.php?page=wc-settings&tab=kb_adapter' );
        echo '<div class="notice notice-error"><p>' . esc_html( $notice ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Einstellungen', 'kb' ) . '</a></p></div>';
    }

    /**
     * Admin Seite für Tracking Objekte.
     */
    public function render_admin_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Sendungsverfolgung', 'kb' ) . '</h1>';

        if ( isset( $_POST['kb_tracking_refresh'] ) && check_admin_referer( 'kb_tracking_refresh', '_kb_tracking_nonce' ) ) {
            if ( $this->run_tracking() ) {
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Sendungsverfolgung aktualisiert.', 'kb' ) . '</p></div>';
            } else {
                $this->show_admin_notice();
            }
        }

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

        echo '<form method="post">';
        wp_nonce_field( 'kb_tracking_refresh', '_kb_tracking_nonce' );
        echo '<p><input type="submit" name="kb_tracking_refresh" class="button button-secondary" value="' . esc_attr__( 'Jetzt aktualisieren', 'kb' ) . '" /></p>';
        echo '</form>';

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
