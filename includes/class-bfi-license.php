<?php
/**
 * Bootflow PRO License Handler
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BFI_License {
    private $option_key = 'bfi_pro_license_key';
    private $status_key = 'bfi_pro_license_status';
    private $instance_id_key = 'bfi_pro_license_instance_id';

    public function activate( $license_key ) {
        $site_url = get_site_url();
        $body = array(
            'license_key'   => $license_key,
            'instance_name' => $site_url,
        );
        $response = wp_remote_post( 'https://api.lemonsqueezy.com/v1/licenses/activate', array(
            'headers' => array('Accept' => 'application/json'),
            'body'    => json_encode( $body ),
            'timeout' => 20,
        ) );
        if ( is_wp_error( $response ) ) {
            update_option( $this->status_key, 'invalid' );
            return false;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['activated'] ) && $data['activated'] ) {
            update_option( $this->option_key, $license_key );
            update_option( $this->status_key, 'active' );
            if ( isset( $data['instance_id'] ) ) {
                update_option( $this->instance_id_key, $data['instance_id'] );
            }
            return true;
        } else {
            update_option( $this->status_key, 'invalid' );
            return false;
        }
    }

    public function deactivate() {
        $license_key = get_option( $this->option_key );
        $instance_id = get_option( $this->instance_id_key );
        if ( ! $license_key || ! $instance_id ) return false;
        $body = array(
            'license_key'  => $license_key,
            'instance_id'  => $instance_id,
        );
        $response = wp_remote_post( 'https://api.lemonsqueezy.com/v1/licenses/deactivate', array(
            'headers' => array('Accept' => 'application/json'),
            'body'    => json_encode( $body ),
            'timeout' => 20,
        ) );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['deactivated'] ) && $data['deactivated'] ) {
            delete_option( $this->option_key );
            update_option( $this->status_key, 'inactive' );
            delete_option( $this->instance_id_key );
            return true;
        }
        return false;
    }

    public function is_active() {
        return get_option( $this->status_key ) === 'active';
    }

    public function get_status() {
        return get_option( $this->status_key );
    }

    public function get_license_key() {
        return get_option( $this->option_key );
    }
}
