<?php
/*
Plugin Name: WooCommerce User Role Discount
Description: Apply a percentage discount for WooCommerce cart based on user roles.
Version: 1.2.6
Author: William Hare & Copilot
GitHub Plugin URI: xboxhacker/wc-user-role-disocunt
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
    $roles = wp_roles()->roles;
    ?>
    <div class="wrap">
        <h1>Role-Based Discounts</h1>
        <form method="post" action="options.php">
            <table style="width:100%">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Discount Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role_key => $role) : ?>
                        <tr>
                            <td><?php echo esc_html($role['name']); ?></td>
                            <td>
                                <input type="number" name="role_discount_<?php echo esc_attr($role_key); ?>" value="<?php echo esc_attr(get_option('role_discount_' . $role_key, '')); ?>" min="0" max="100" step="1" /> %
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
// Manage user roles page
function manage_user_roles_page() {
    $roles = wp_roles()->roles;
    ?>
    <style>
        table tr:nth-child(even) {
            background-color: #D6EEEE;
        }
    </style>
    <div class="wrap">
        <h1>Manage User Roles</h1>
        <h2>Current User Roles</h2>
        <form method="post" action="">
            <?php wp_nonce_field('delete_role_verify', 'delete_role_nonce'); ?>
            <table style="width:20%">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Role Key</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role_key => $role) : ?>
                        <tr>
                            <td><?php echo esc_html($role['name']); ?></td>
                            <td><?php echo esc_html($role_key); ?></td>
                            <td>
                                <?php if ($role_key !== 'administrator') : ?>
                                    <input type="checkbox" name="delete_roles[]" value="<?php echo esc_attr($role_key); ?>">
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <input type="submit" name="delete_role" value="Delete Selected Roles"
                   onclick="return confirm('Are you sure you want to delete the selected roles? This action cannot be undone.');">
        </form>
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
            }
        }
    }
}

// Add auto-update functionality
add_filter('pre_set_site_transient_update_plugins', 'github_plugin_update_check');

function github_plugin_update_check($transient) {
    // Check if the transient and plugin data are set
    if (empty($transient->checked)) {
        return $transient;
    }

    // Get plugin data
    $plugin_slug = plugin_basename(__FILE__);
    $plugin_data = get_plugin_data(__FILE__);
    $current_version = $plugin_data['Version'];
    $response = wp_remote_get('https://api.github.com/repos/xboxhacker/wc-user-role-disocunt/releases/latest');

    if (is_wp_error($response)) {
        return $transient;
    }

    $release = json_decode(wp_remote_retrieve_body($response));
    if (version_compare($current_version, $release->tag_name, '<')) {
        $transient->response[$plugin_slug] = (object) [
            'id' => $release->id,
            'slug' => $plugin_slug,
            'plugin' => $plugin_slug,
            'new_version' => $release->tag_name,
            'url' => $release->html_url,
            'package' => $release->zipball_url,
        ];
    }

    return $transient;
}

// Fetch plugin information
add_filter('plugins_api', 'github_plugin_information', 20, 3);

function github_plugin_information($false, $action, $response) {
    if (empty($response->slug) || $response->slug !== plugin_basename(__FILE__)) {
        return $false;
    }

    $response->slug = plugin_basename(__FILE__);
    $response->name = 'WooCommerce User Role Discount';
    $response->version = '1.2.6';
    $response->author = 'William Hare & Copilot';
    $response->homepage = 'https://github.com/xboxhacker/wc-user-role-disocunt';
    $response->download_link = 'https://github.com/xboxhacker/wc-user-role-disocunt/archive/refs/heads/main.zip';

    return $response;
}

// Clear GitHub API cache
add_action('upgrader_process_complete', 'clear_github_api_cache', 10, 2);

function clear_github_api_cache($upgrader_object, $options) {
    if ($options['action'] === 'update' && $options['type'] === 'plugin') {
        delete_site_transient('update_plugins');
    }
}

