<?php
/*
Plugin Name: WooCommerce User Role Discount
Plugin URI: https://github.com/xboxhacker/wc-user-role-discount
Description: Woocommerce discount for specific user roles.
Version: 1.7.0
Author: William Hare & Copilot
Author URI: https://github.com/xboxhacker
License: GPL2
*/

// Add new user role
function add_new_user_role() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $role_name = sanitize_text_field($_POST['new_role_name']);
    $role_display_name = sanitize_text_field($_POST['new_role_display_name']);

    if (!empty($role_name) && !empty($role_display_name)) {
        add_role($role_name, $role_display_name);
        update_option('role_discount_' . $role_name, 0);
    }
}

// Delete user role
function delete_user_role() {
    if (!current_user_can('manage_options')) {
        error_log('User does not have permission to manage options.');
        return;
    }

    if (isset($_POST['delete_roles'])) {
        foreach ($_POST['delete_roles'] as $role_name) {
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

// Function to refresh the role list
function refresh_role_list() {
    echo '<script>location.reload();</script>';
}

// Removed auto-update functionality

// Add WooCommerce role discount
add_action('woocommerce_cart_calculate_fees', 'apply_role_discount');

function apply_role_discount() {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    foreach ($roles as $role) {
        $discount = get_option('role_discount_' . $role);
        if ($discount) {
            WC()->cart->add_fee(__('Role Discount', 'wc-user-role-discount'), -$discount);
        }
    }
}

// Add settings page
add_action('admin_menu', 'role_discount_menu');

function role_discount_menu() {
    add_options_page(
        'Role Discounts',
        'Role Discounts',
        'manage_options',
        'role-discounts',
        'role_discount_options_page'
    );
}

function role_discount_options_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Role Discounts', 'wc-user-role-discount'); ?></h1>
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
    $roles = get_editable_roles();
    foreach ($roles as $role_name => $role_info) {
        register_setting('role_discount_options', 'role_discount_' . $role_name);
        add_settings_field(
            'role_discount_' . $role_name,
            $role_info['name'],
            'role_discount_field_callback',
            'role_discount_options',
            'default',
            array('role_name' => $role_name)
        );
    }
}

function role_discount_field_callback($args) {
    $role_name = $args['role_name'];
    $discount = get_option('role_discount_' . $role_name);
    echo '<input type="number" name="role_discount_' . $role_name . '" value="' . esc_attr($discount) . '" />';
}
?>
