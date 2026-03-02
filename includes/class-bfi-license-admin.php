<?php
/**
 * Bootflow PRO License Admin Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class BFI_License_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_license_page' ) );
        add_action( 'admin_post_bfi_activate_license', array( $this, 'handle_activate' ) );
        add_action( 'admin_post_bfi_deactivate_license', array( $this, 'handle_deactivate' ) );
    }

    public function add_license_page() {
        add_submenu_page(
            'bootflow-product-importer',
            __( 'PRO License', 'bootflow-product-importer' ),
            __( 'PRO License', 'bootflow-product-importer' ),
            'manage_options',
            'bfi-pro-license',
            array( $this, 'render_license_page' )
        );
    }

    public function render_license_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $license = new BFI_License();
        $status = $license->get_status();
        $license_key = $license->get_license_key();
        $nonce = wp_create_nonce( 'bfi_license_action' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Bootflow PRO License', 'bootflow-product-importer' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="bfi_activate_license">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="bfi_license_key"><?php esc_html_e( 'License Key', 'bootflow-product-importer' ); ?></label></th>
                        <td><input type="text" id="bfi_license_key" name="bfi_license_key" value="<?php echo esc_attr( $license_key ); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Activate License', 'bootflow-product-importer' ); ?></button>
                    <?php if ( $status === 'active' ) : ?>
                        <span style="color:green;font-weight:bold;">&#10003; <?php esc_html_e( 'Active', 'bootflow-product-importer' ); ?></span>
                    <?php else : ?>
                        <span style="color:red;font-weight:bold;">&#10007; <?php esc_html_e( 'Inactive/Invalid', 'bootflow-product-importer' ); ?></span>
                    <?php endif; ?>
                </p>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                <input type="hidden" name="action" value="bfi_deactivate_license">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Deactivate License', 'bootflow-product-importer' ); ?></button>
            </form>
        </div>
        <?php
    }

    public function handle_activate() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'bfi_license_action' );
        $license_key = isset( $_POST['bfi_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bfi_license_key'] ) ) : '';
        $license = new BFI_License();
        $license->activate( $license_key );
        wp_redirect( admin_url( 'admin.php?page=bfi-pro-license' ) );
        exit;
    }

    public function handle_deactivate() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'bfi_license_action' );
        $license = new BFI_License();
        $license->deactivate();
        wp_redirect( admin_url( 'admin.php?page=bfi-pro-license' ) );
        exit;
    }
}

add_action( 'plugins_loaded', function() {
    if ( is_admin() ) {
        new BFI_License_Admin();
    }
} );
