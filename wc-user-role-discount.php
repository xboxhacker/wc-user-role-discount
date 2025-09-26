<?php
/*
Plugin Name: WooCommerce User Role Discount
Plugin URI: https://github.com/xboxhacker/wc-user-role-discount
Description: WooCommerce percentage discount for specific user roles (applies only to product subtotal, excludes shipping & tax).
Version: 1.9.1
Author: William Hare & Copilot
Author URI: https://github.com/xboxhacker
License: GPL2
*/

// Add new user role
function add_new_user_role() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $role_name         = sanitize_text_field($_POST['new_role_name'] ?? '');
    $role_display_name = sanitize_text_field($_POST['new_role_display_name'] ?? '');

    if ($role_name && $role_display_name) {
        add_role($role_name, $role_display_name);
        update_option('role_discount_' . $role_name, 0); // initialize percent
    }
}

// Delete user role
function delete_user_role() {
    if (!current_user_can('manage_options')) {
        error_log('User does not have permission to manage options.');
        return;
    }

    if (isset($_POST['delete_roles'])) {
        foreach ((array) $_POST['delete_roles'] as $role_name) {
            if ($role_name === 'administrator') {
                echo '<script>alert("Cannot delete the Administrator role.");</script>';
                continue;
            }
            if (!empty($role_name)) {
                if (get_role($role_name)) {
                    remove_role($role_name);
                    delete_option('role_discount_' . $role_name);
                } else {
                    echo '<script>alert("Role does not exist.");</script>';
                }
            } else {
                error_log('Role name is empty.');
            }
        }
    } else {
        error_log('No roles selected for deletion.');
    }
}

// Handle delete role action
if (isset($_POST['delete_role'])) {
    delete_user_role();
}

// (Legacy / unused)
function refresh_role_list() {
    echo '<script>location.reload();</script>';
}

// Apply percentage discount based on user roles
add_action('woocommerce_cart_calculate_fees', 'apply_role_discount');
function apply_role_discount() {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    $user  = wp_get_current_user();
    $roles = (array) $user->roles;

    $total_percent = 0.0;
    foreach ($roles as $role) {
        $percent = get_option('role_discount_' . $role);
        if ($percent !== false && is_numeric($percent) && $percent > 0) {
            $total_percent += (float) $percent;
        }
    }

    if ($total_percent <= 0) {
        return;
    }

    /*
     * BASE SELECTION:
     * Using get_subtotal_ex_tax(): product subtotal BEFORE coupons, excluding tax & shipping.
     * This ensures discount applies only to products and not to tax or shipping.
     *
     * If you prefer discount AFTER coupons, replace with:
     * $base_amount = WC()->cart->get_cart_contents_total(); // after coupons, ex tax, products only
     */
    $base_amount = WC()->cart->get_subtotal_ex_tax();

    if ($base_amount <= 0) {
        return;
    }

    $discount_amount = $base_amount * ($total_percent / 100);

    if ($discount_amount > 0) {
        $label = sprintf(
            __('Role Discount (%s%%)', 'wc-user-role-discount'),
            rtrim(rtrim(number_format($total_percent, 2, '.', ''), '0'), '.')
        );

        /*
         * add_fee parameters:
         *  - $label
         *  - negative amount (discount)
         *  - $taxable = false ensures this discount does NOT reduce tax lines (so tax is based on original product subtotal).
         *    Set to true if you want tax to recalc on discounted amount.
         */
        WC()->cart->add_fee($label, -$discount_amount, false);
    }
}

// Settings page under Users
add_action('admin_menu', 'role_discount_menu');
function role_discount_menu() {
    add_users_page(
        __('Role Discounts', 'wc-user-role-discount'),
        __('Role Discounts', 'wc-user-role-discount'),
        'manage_options',
        'role-discounts',
        'role_discount_options_page'
    );
}

function role_discount_options_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Role Discounts', 'wc-user-role-discount'); ?></h1>
        <p><?php _e('Set percentage discounts for each user role. Percentages are summed if a user has multiple roles. Discount base: product subtotal only (no tax, no shipping).', 'wc-user-role-discount'); ?></p>
        <p><?php _e('Currently applied BEFORE coupons. Ask to change if you want it applied after coupons or to cap the total.', 'wc-user-role-discount'); ?></p>
        <form method="post" action="options.php">
            <?php
            settings_fields('role_discount_options');
            do_settings_sections('role_discount_options');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'role_discount_settings');
function role_discount_settings() {
    add_settings_section(
        'role_discount_section',
        __('Role Discount Settings', 'wc-user-role-discount'),
        function () {
            echo '<p>' . esc_html__('Enter percentage discounts (e.g., 5 for 5%). Values from multiple roles are added. Applies only to products (ex tax & shipping).', 'wc-user-role-discount') . '</p>';
        },
        'role_discount_options'
    );

    $roles = get_editable_roles();
    foreach ($roles as $role_name => $role_info) {
        register_setting('role_discount_options', 'role_discount_' . $role_name, [
            'type'              => 'number',
            'sanitize_callback' => function($value) {
                $value = is_numeric($value) ? (float) $value : 0;
                if ($value < 0) $value = 0;
                return $value;
            },
            'default'           => 0,
        ]);

        add_settings_field(
            'role_discount_' . $role_name,
            esc_html($role_info['name']),
            'role_discount_field_callback',
            'role_discount_options',
            'role_discount_section',
            ['role_name' => $role_name]
        );
    }
}

function role_discount_field_callback($args) {
    $role_name = $args['role_name'];
    $discount  = get_option('role_discount_' . $role_name, 0);
    echo '<input type="number" step="0.01" min="0" style="width:120px;" name="role_discount_' . esc_attr($role_name) . '" value="' . esc_attr($discount) . '" /> ';
    echo '<span class="description">' . esc_html__('Percent discount (products only, before coupons).', 'wc-user-role-discount') . '</span>';
}
?>
