<?php
/**
 * Daten-Speicher für KB_Tracking Objekte.
 *
 * @package WooCommerce_KingBear_Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! interface_exists( 'WC_Object_Data_Store_Interface', false ) ) {
    return;
}

class KB_Tracking_Data_Store implements WC_Object_Data_Store_Interface {

    /**
     * Option Key für gespeicherte IDs.
     */
    const IDS_OPTION = 'kb_tracking_ids';

    public function read( &$tracking ) {
        $id   = $tracking->get_id();
        $data = get_option( $this->get_option_key( $id ), array() );
        if ( ! empty( $data ) && is_array( $data ) ) {
            $tracking->set_props( $data );
            $tracking->set_object_read( true );
        }
    }

    public function create( &$tracking ) {
        $id = $tracking->get_id();
        if ( ! $id ) {
            $id = $tracking->get_tracking_id();
            $tracking->set_id( $id );
        }
        update_option( $this->get_option_key( $id ), $tracking->get_data() );
        $this->add_id_to_index( $id );
        $tracking->set_object_read( true );
    }

    public function update( &$tracking ) {
        $id = $tracking->get_id();
        update_option( $this->get_option_key( $id ), $tracking->get_data() );
        $this->add_id_to_index( $id );
    }

    public function delete( &$tracking, $args = array() ) {
        $id = $tracking->get_id();
        delete_option( $this->get_option_key( $id ) );
        $this->remove_id_from_index( $id );
    }

    public function read_meta( &$tracking ) {
        return array();
    }

    public function delete_meta( &$tracking, $meta ) {
        return array();
    }

    public function add_meta( &$tracking, $meta ) {
        return 0;
    }

    public function update_meta( &$tracking, $meta ) {
        // Kein Meta-Support notwendig.
    }

    protected function get_option_key( $id ) {
        return 'kb_tracking_' . $id;
    }

    protected function add_id_to_index( $id ) {
        $ids = get_option( self::IDS_OPTION, array() );
        if ( ! in_array( $id, $ids, true ) ) {
            $ids[] = $id;
            update_option( self::IDS_OPTION, $ids );
        }
    }

    protected function remove_id_from_index( $id ) {
        $ids = get_option( self::IDS_OPTION, array() );
        $ids = array_diff( $ids, array( $id ) );
        update_option( self::IDS_OPTION, $ids );
    }
}
