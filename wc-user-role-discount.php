<?php
/*
Plugin Name: WooCommerce User Role Discount
Description: Apply a percentage discount for WooCommerce cart based on user roles.
Version: 1.2.4
Author: William Hare & Copilot
GitHub Plugin URI: xboxhacker/wc-user-role-disocunt
*/

...

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
    $response->version = '1.2.3';
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

...
