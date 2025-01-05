<?php
/*
Plugin Name: WooCommerce User Role Discount
Description: Apply a percentage discount for WooCommerce cart based on user roles.
Version: 1.4.0
Author: William Hare & Copilot
GitHub Plugin URI: xboxhacker/wc-user-role-disocunt
*/

// Use plugins_loaded hook to ensure WordPress is fully loaded
add_action('plugins_loaded', 'wc_user_role_discount_init');

function wc_user_role_discount_init() {
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
        }
    }

    // Settings page
    function role_discount_settings_page() {
        $roles = wp_roles()->roles;
        ?>
        <div class="wrap">
            <h1>Role-Based Discounts</h1><br>
            <form method="post" action="options.php">
                <?php
                settings_fields('role_discount_options');
                do_settings_sections('role-discounts');
                wp_nonce_field('role_discount_settings_save', 'role_discount_nonce');
                ?>
                <style>
                    th {
                        text-align: left;
                    }
                </style>
                <table style="width:30%">
                    <thead>
                        <tr>
                            <th style="width:30%">Role Name</th>
                            <th style="width:30%">Discount Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role_key => $role) : ?>
                            <tr>
                                <td><?php echo esc_html($role['name']); ?></td>
                                <td>
                                    <input type="number" name="role_discount_<?php echo esc_attr($role_key); ?>" value="<?php echo esc_attr(get_option('role_discount_' . $role_key, '')); ?>" min="0" m[...]
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
            th {
                text-align: left;
            }
        </style>
        <div class="wrap">
            <h1>Manage User Roles</h1>
            <br>
            <form method="post" action="">
                <?php wp_nonce_field('manage_user_roles_action', 'manage_user_roles_nonce'); ?>
                <table style="width:20%">
                    <thead>
                        <tr>
                            <th>Display Name</th>
                            <th>Role Name</th>
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
                <br>
                <input type="submit" name="delete_role" value="Delete Selected Roles"
                       onclick="return confirm('Are you sure you want to delete the selected roles? This action cannot be undone.');">
            </form>
            <br>
            <hr style='width:100%'/>
            <br>
            <h2>Add New User Role</h2>
            <form method="post" action="">
                <?php wp_nonce_field('add_new_role_action', 'add_new_role_nonce'); ?>
                <label for="new_role_name">Role Name:</label>
                <input type="text" id="new_role_name" name="new_role_name" required>
                <label for="new_role_display_name">Display Name:</label>
                <input type="text" id="new_role_display_name" name="new_role_display_name" required>
                <input type="submit" name="add_new_role" value="Add Role">
            </form>
            <?php
            if (isset($_POST['add_new_role'])) {
                if (!isset($_POST['add_new_role_nonce']) || !wp_verify_nonce($_POST['add_new_role_nonce'], 'add_new_role_action')) {
                    die('Security check failed');
                }
                add_new_user_role();
                refresh_role_list(); // Refresh role list after adding a new role
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
            if (!isset($_POST['manage_user_roles_nonce']) || !wp_verify_nonce($_POST['manage_user_roles_nonce'], 'manage_user_roles_action')) {
                die('Security check failed');
            }

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

    // Auto-update functionality and other actions remain unchanged as they do not use wp_get_current_user()
    add_filter('pre_set_site_transient_update_plugins', 'github_plugin_update_check');
    add_filter('plugins_api', 'github_plugin_information', 20, 3);
    add_action('upgrader_process_complete', 'clear_github_api_cache', 10, 2);
}

// The following functions should be defined outside the plugins_loaded hook if they are to be used elsewhere
function github_plugin_update_check($transient) {
    // ... (rest of the function remains unchanged)
}

function github_plugin_information($false, $action, $response) {
    // ... (rest of the function remains unchanged)
}

function clear_github_api_cache($upgrader_object, $options) {
    // ... (rest of the function remains unchanged)
}
