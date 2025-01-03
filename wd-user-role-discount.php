<?php

namespace WPTurbo;

use WC_Discount_Role;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Discount_Role {

    const OPTION_NAME = 'wpturbo_role_discounts';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'create_admin_menu' ] );
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_role_discount' ] );
        add_action( 'woocommerce_review_order_before_order_total', [ $this, 'display_discount_label' ] );
    }

    public function create_admin_menu() {
        add_menu_page(
            __( "Role Discounts", 'wpturbo' ),
            __( "Role Discounts", 'wpturbo' ),
            'manage_options',
            'role-discounts',
            [ $this, 'discount_page' ]
        );
    }

    public function discount_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $role_discounts = get_option( self::OPTION_NAME, [] );

        if ( isset( $_POST['role_discounts'] ) ) {
            check_admin_referer( 'update_role_discounts' );

            $role_discounts = array_map( 'sanitize_text_field', $_POST['role_discounts'] );
            update_option( self::OPTION_NAME, $role_discounts );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Role Discounts', 'wpturbo' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'update_role_discounts' ); ?>
                <table class="form-table">
                    <?php foreach ( wp_roles()->roles as $role => $details ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $details['name'] ); ?></th>
                            <td>
                                <input type="number" name="role_discounts[<?php echo esc_attr( $role ); ?>]" value="<?php echo esc_attr( isset( $role_discounts[$role] ) ? $role_discounts[$role] : '' ); ?>" min="0" max="100" step="1" />
                                <span class="description"><?php esc_html_e( 'Discount percentage for this role.', 'wpturbo' ); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button( __( 'Save Discounts', 'wpturbo' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function apply_role_discount() {
        $user = wp_get_current_user();
        $role_discounts = get_option( self::OPTION_NAME, [] );

        foreach ( $user->roles as $role ) {
            if ( isset( $role_discounts[$role] ) && $role_discounts[$role] > 0 ) {
                $discount = ( $role_discounts[$role] / 100 ) * WC()->cart->subtotal;
                WC()->cart->add_fee( __( 'Discount for ' . ucfirst( $role ), 'wpturbo' ), -$discount );
            }
        }
    }

    public function display_discount_label() {
        $user = wp_get_current_user();
        $role_discounts = get_option( self::OPTION_NAME, [] );

        foreach ( $user->roles as $role ) {
            if ( isset( $role_discounts[$role] ) && $role_discounts[$role] > 0 ) {
                echo '<tr class="order-discount">';
                echo '<th>' . esc_html__( 'Discount for ' . ucfirst( $role ), 'wpturbo' ) . '</th>';
                echo '<td>-' . esc_html( $role_discounts[$role] ) . '%</td>';
                echo '</tr>';
            }
        }
    }
}

new WC_Discount_Role();
