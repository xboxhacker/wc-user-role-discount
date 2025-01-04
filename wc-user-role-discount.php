<?php
/*
Plugin Name: WooCommerce User Role Discount
Description: Apply a percentage discount for WooCommerce cart based on user roles.
Version: 1.2.1
Author: William Hare & Copilot
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
    add_submenu_page(
        'role-discounts',
        'Manage User Roles',
        'Manage User Roles',
        'manage_options',
        'manage-user-roles',
        'manage_user_roles_page'
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

// Manage user roles page
function manage_user_roles_page() {
    $roles = wp_roles()->roles;
    ?>
    <div class="wrap">
        <h1>Manage User Roles</h1>
        <h2>Current User Roles</h2>
        <ul>
            <?php foreach ($roles as $role_key => $role) : ?>
                <li><?php echo esc_html($role['name']); ?> (<?php echo esc_html($role_key); ?>)</li>
            <?php endforeach; ?>
        </ul>
        <h2>Add New User Role</h2>
        <form method="post" action="">
            <?php wp_nonce_field('add_new_role_verify', 'add_new_role_nonce'); ?>
            <label for="new_role_name">Role Name:</label>
            <input type="text" id="new_role_name" name="new_role_name" required>
            <label for="new_role_display_name">Display Name:</label>
            <input type="text" id="new_role_display_name" name="new_role_display_name" required>
            <input type="submit" name="add_new_role" value="Add Role">
        </form>
        <?php
        if (isset($_POST['add_new_role'])) {
            add_new_user_role();
        }
        ?>
        <h2>Delete User Role</h2>
        <form method="post" action="">
            <?php wp_nonce_field('delete_role_verify', 'delete_role_nonce'); ?>
            <label for="delete_role_name">Role Name:</label>
            <input type="text" id="delete_role_name" name="delete_role_name" required>
            <input type="submit" name="delete_role" value="Delete Role"
                   onclick="return confirm('Are you sure you want to delete this role? This action cannot be undone.');">
        </form>
        <?php
        if (isset($_POST['delete_role'])) {
            delete_user_role();
        }
        ?>
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

// Add new user role
function add_new_user_role() {
    if (!isset($_POST['add_new_role_nonce']) || !wp_verify_nonce($_POST['add_new_role_nonce'], 'add_new_role_verify')) {
        return;
    }

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
    if (!isset($_POST['delete_role_nonce']) || !wp_verify_nonce($_POST['delete_role_nonce'], 'delete_role_verify')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $role_name = sanitize_text_field($_POST['delete_role_name']);
    if ($role_name === 'administrator') {
        echo '<script>alert("Cannot delete the Administrator role.");</script>';
        return;
    }

    if (!empty($role_name)) {
        if (get_role($role_name)) {
            remove_role($role_name);
            delete_option('role_discount_' . $role_name);
        } else {
            echo '<script>alert("Role does not exist.");</script>';
        }
    }
}
