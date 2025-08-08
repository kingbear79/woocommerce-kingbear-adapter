<?php
/**
 * Datenobjekt für die Sendungsverfolgung.
 *
 * @package WooCommerce_KingBear_Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Data', false ) ) {
    return;
}

require_once __DIR__ . '/class-kb-tracking-data-store.php';

class KB_Tracking extends WC_Data {

    protected $object_type = 'kb_tracking';

    protected $data = array(
        'shipment_id'     => 0,
        'tracking_id'     => '',
        'status'          => '',
        'status_timestamp'=> '',
        'delivered'       => false,
        'is_return'       => false,
        'raw_data'        => array(),
        'events'          => array(),
        'do_tracking'     => true,
    );

    /**
     * Konstruktor.
     *
     * @param int $id Shipment ID.
     */
    public function __construct( $id = 0 ) {
        parent::__construct( $id );
        $this->data_store = new KB_Tracking_Data_Store();
        if ( $id ) {
            $this->set_id( $id );
            $this->data_store->read( $this );
        }
    }

    /**
     * Speichert das Objekt.
     */
    public function save() {
        if ( 0 === $this->get_id() ) {
            $this->data_store->create( $this );
        } else {
            $this->data_store->update( $this );
        }
    }

    /**
     * ID Getter/Setter (Shipment ID).
     */
    public function get_id( $context = 'view' ) {
        return $this->get_prop( 'shipment_id', $context );
    }

    public function set_id( $id ) {
        $this->set_prop( 'shipment_id', absint( $id ) );
    }

    // Tracking ID.
    public function get_tracking_id( $context = 'view' ) {
        return $this->get_prop( 'tracking_id', $context );
    }

    public function set_tracking_id( $value ) {
        $this->set_prop( 'tracking_id', wc_clean( $value ) );
    }

    // Status.
    public function get_status( $context = 'view' ) {
        return $this->get_prop( 'status', $context );
    }

    public function set_status( $value ) {
        $this->set_prop( 'status', wc_clean( $value ) );
    }

    // Status Timestamp.
    public function get_status_timestamp( $context = 'view' ) {
        return $this->get_prop( 'status_timestamp', $context );
    }

    public function set_status_timestamp( $value ) {
        $this->set_prop( 'status_timestamp', wc_clean( $value ) );
    }

    // Delivered.
    public function get_delivered( $context = 'view' ) {
        return (bool) $this->get_prop( 'delivered', $context );
    }

    public function set_delivered( $value ) {
        $this->set_prop( 'delivered', (bool) $value );
    }

    // Is return.
    public function get_is_return( $context = 'view' ) {
        return (bool) $this->get_prop( 'is_return', $context );
    }

    public function set_is_return( $value ) {
        $this->set_prop( 'is_return', (bool) $value );
    }

    // Raw data.
    public function get_raw_data( $context = 'view' ) {
        return $this->get_prop( 'raw_data', $context );
    }

    public function set_raw_data( $value ) {
        $this->set_prop( 'raw_data', (array) $value );
    }

    // Events.
    public function get_events( $context = 'view' ) {
        return $this->get_prop( 'events', $context );
    }

    public function set_events( $value ) {
        $this->set_prop( 'events', (array) $value );
    }

    // Do tracking.
    public function get_do_tracking( $context = 'view' ) {
        return (bool) $this->get_prop( 'do_tracking', $context );
    }

    public function set_do_tracking( $value ) {
        $this->set_prop( 'do_tracking', (bool) $value );
    }
}
