<?php
/*
Plugin Name: WooCommerce User Role Discount
Description: Apply a percentage discount for WooCommerce cart based on user roles.
Version: 1.0.0
Author: WIlliam Hare & Copilot
*/

// Add admin menu
add_action('admin_menu', 'role_discount_menu');

function role_discount_menu() {
    add_menu_page(
        'Role-Based Discounts',
        'Role Discounts',
        'manage_options',
        'role-discounts',
        'role_discount_settings_page',
        'dashicons-admin-generic',
        20
    );
}

// Register settings
add_action('admin_init', 'role_discount_settings');

function role_discount_settings() {
    $roles = wp_roles()->roles;
    foreach ($roles as $role_key => $role) {
        register_setting('role_discount_options', 'role_discount_' . $role_key);
        add_settings_section(
            'role_discount_section_' . $role_key,
            $role['name'] . ' Discount',
            null,
            'role-discounts'
        );
        add_settings_field(
            'role_discount_' . $role_key,
            'Discount Percentage',
            'role_discount_field_callback',
            'role-discounts',
            'role_discount_section_' . $role_key,
            ['role_key' => $role_key]
        );
    }
}

function role_discount_field_callback($args) {
    $role_key = $args['role_key'];
    $value = get_option('role_discount_' . $role_key, '');
    echo '<input type="number" name="role_discount_' . $role_key . '" value="' . esc_attr($value) . '" min="0" max="100" step="1" /> %';
}

// Settings page
function role_discount_settings_page() {
    ?>
    <div class="wrap">
        <h1>Role-Based Discounts</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('role_discount_options');
            do_settings_sections('role-discounts');
            wp_nonce_field('role_discount_options_verify', 'role_discount_nonce');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Apply discount based on user role
add_action('woocommerce_cart_calculate_fees', 'apply_role_discount');

function apply_role_discount() {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $user = wp_get_current_user();
    $roles = $user->roles;
    foreach ($roles as $role) {
        $discount = get_option('role_discount_' . $role, 0);
        if ($discount > 0) {
            $discount_amount = WC()->cart->get_subtotal() * ($discount / 100);
            WC()->cart->add_fee(ucfirst($role) . ' Discount', -$discount_amount);
        }
    }
}

// Security considerations
add_action('admin_init', 'role_discount_settings_security_check');

function role_discount_settings_security_check() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!isset($_POST['role_discount_nonce']) || !wp_verify_nonce($_POST['role_discount_nonce'], 'role_discount_options_verify')) {
            return;
        }
    }
}
