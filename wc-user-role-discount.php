<?php
/**
 * Plugin Name: WooCommerce User Role Discount
 * Plugin URI: https://github.com/xboxhacker/wc-user-role-discount
 * Description: WooCommerce percentage discount and optional free shipping for specific user roles (applies only to product subtotal, excludes shipping & tax).
 * Version: 2.0.1
 * Author: William Hare & Copilot
 * Author URI: https://github.com/xboxhacker
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.8
 * WC requires at least: 8.0
 * WC tested up to: 9.8
 * Text Domain: wc-user-role-discount
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_URD_VERSION', '2.0.1' );

/**
 * Declare compatibility with WooCommerce features.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Bail early if WooCommerce is not active.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					esc_html_e( 'WooCommerce User Role Discount requires WooCommerce to be installed and active.', 'wc-user-role-discount' );
					echo '</p></div>';
				}
			);
			return;
		}

		add_action( 'woocommerce_cart_calculate_fees', 'wc_urd_apply_role_discount' );
		add_filter( 'woocommerce_package_rates', 'wc_urd_apply_role_free_shipping', 100, 2 );
	}
);

/**
 * Handle add-role form submission.
 */
add_action(
	'admin_init',
	function () {
		if ( ! isset( $_POST['wc_urd_add_role'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['wc_urd_add_role_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_urd_add_role_nonce'] ) ), 'wc_urd_add_role' ) ) {
			return;
		}

		$role_name         = isset( $_POST['new_role_name'] ) ? sanitize_key( wp_unslash( $_POST['new_role_name'] ) ) : '';
		$role_display_name = isset( $_POST['new_role_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_role_display_name'] ) ) : '';

		if ( ! $role_name || ! $role_display_name ) {
			add_settings_error( 'wc_urd_messages', 'wc_urd_role_missing', __( 'Both role slug and display name are required.', 'wc-user-role-discount' ), 'error' );
			return;
		}

		if ( get_role( $role_name ) ) {
			add_settings_error( 'wc_urd_messages', 'wc_urd_role_exists', __( 'A role with that slug already exists.', 'wc-user-role-discount' ), 'error' );
			return;
		}

		add_role( $role_name, $role_display_name, array( 'read' => true ) );
		update_option( 'role_discount_' . $role_name, 0 );
		update_option( 'role_free_shipping_' . $role_name, 0 );

		add_settings_error( 'wc_urd_messages', 'wc_urd_role_added', __( 'User role added.', 'wc-user-role-discount' ), 'success' );
	}
);

/**
 * Apply percentage discount based on user roles.
 */
function wc_urd_apply_role_discount() {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$user          = wp_get_current_user();
	$roles         = (array) $user->roles;
	$total_percent = 0.0;

	foreach ( $roles as $role ) {
		$percent = get_option( 'role_discount_' . $role );
		if ( false !== $percent && is_numeric( $percent ) && $percent > 0 ) {
			$total_percent += (float) $percent;
		}
	}

	if ( $total_percent <= 0 ) {
		return;
	}

	$base_amount = WC()->cart->get_subtotal_ex_tax();

	if ( $base_amount <= 0 ) {
		return;
	}

	$discount_amount = $base_amount * ( $total_percent / 100 );

	if ( $discount_amount > 0 ) {
		$label = sprintf(
			/* translators: %s: discount percentage */
			__( 'Role Discount (%s%%)', 'wc-user-role-discount' ),
			rtrim( rtrim( number_format( $total_percent, 2, '.', '' ), '0' ), '.' )
		);

		WC()->cart->add_fee( $label, -$discount_amount, false );
	}
}

/**
 * Whether the current user qualifies for role-based free shipping.
 */
function wc_urd_user_has_free_shipping() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	$user = wp_get_current_user();

	foreach ( (array) $user->roles as $role ) {
		if ( (int) get_option( 'role_free_shipping_' . $role, 0 ) === 1 ) {
			return true;
		}
	}

	return false;
}

/**
 * Zero out shipping rates for qualifying roles while keeping package data intact.
 *
 * @param array $rates   Available shipping rates.
 * @param array $package Shipping package.
 * @return array
 */
function wc_urd_apply_role_free_shipping( $rates, $package ) {
	if ( ! wc_urd_user_has_free_shipping() || empty( $rates ) ) {
		return $rates;
	}

	foreach ( $rates as $rate_id => $rate ) {
		$rates[ $rate_id ]->cost = 0;

		if ( ! empty( $rate->taxes ) && is_array( $rate->taxes ) ) {
			foreach ( $rate->taxes as $tax_key => $tax_amount ) {
				$rates[ $rate_id ]->taxes[ $tax_key ] = 0;
			}
		}
	}

	return $rates;
}

add_action( 'admin_menu', 'wc_urd_role_discount_menu' );

/**
 * Register settings page under Users.
 */
function wc_urd_role_discount_menu() {
	add_users_page(
		__( 'Role Discounts', 'wc-user-role-discount' ),
		__( 'Role Discounts', 'wc-user-role-discount' ),
		'manage_options',
		'role-discounts',
		'wc_urd_role_discount_options_page'
	);
}

/**
 * Render settings page.
 */
function wc_urd_role_discount_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>
			<?php esc_html_e( 'Role Discounts', 'wc-user-role-discount' ); ?>
			<span style="font-size:14px;font-weight:normal;color:#646970;margin-left:8px;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: plugin version number */
						__( 'Version %s', 'wc-user-role-discount' ),
						WC_URD_VERSION
					)
				);
				?>
			</span>
		</h1>
		<p><?php esc_html_e( 'Set percentage discounts for each user role. Percentages are summed if a user has multiple roles. Discount base: product subtotal only (no tax, no shipping).', 'wc-user-role-discount' ); ?></p>

		<?php settings_errors( 'wc_urd_messages' ); ?>

		<h2><?php esc_html_e( 'Add User Role', 'wc-user-role-discount' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'wc_urd_add_role', 'wc_urd_add_role_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="new_role_name"><?php esc_html_e( 'Role Slug', 'wc-user-role-discount' ); ?></label>
					</th>
					<td>
						<input type="text" id="new_role_name" name="new_role_name" class="regular-text" placeholder="wholesale" />
						<p class="description"><?php esc_html_e( 'Lowercase letters, numbers, and underscores only (e.g. wholesale).', 'wc-user-role-discount' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="new_role_display_name"><?php esc_html_e( 'Display Name', 'wc-user-role-discount' ); ?></label>
					</th>
					<td>
						<input type="text" id="new_role_display_name" name="new_role_display_name" class="regular-text" placeholder="<?php esc_attr_e( 'Wholesale Customer', 'wc-user-role-discount' ); ?>" />
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Add User Role', 'wc-user-role-discount' ), 'secondary', 'wc_urd_add_role', false ); ?>
		</form>

		<hr />

		<form method="post" action="options.php">
			<?php
			settings_fields( 'role_discount_options' );
			do_settings_sections( 'role_discount_options' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

add_action( 'admin_init', 'wc_urd_role_discount_settings' );

/**
 * Register role discount and free-shipping settings.
 */
function wc_urd_role_discount_settings() {
	add_settings_section(
		'role_discount_section',
		__( 'Role Discount Settings', 'wc-user-role-discount' ),
		function () {
			echo '<p>' . esc_html__( 'Enter percentage discounts (e.g., 5 for 5%). Values from multiple roles are added. Applies only to products (ex tax & shipping).', 'wc-user-role-discount' ) . '</p>';
		},
		'role_discount_options'
	);

	$roles = get_editable_roles();

	foreach ( $roles as $role_name => $role_info ) {
		register_setting(
			'role_discount_options',
			'role_discount_' . $role_name,
			array(
				'type'              => 'number',
				'sanitize_callback' => function ( $value ) {
					$value = is_numeric( $value ) ? (float) $value : 0;
					if ( $value < 0 ) {
						$value = 0;
					}
					return $value;
				},
				'default'           => 0,
			)
		);

		register_setting(
			'role_discount_options',
			'role_free_shipping_' . $role_name,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => function ( $value ) {
					return ! empty( $value ) ? 1 : 0;
				},
				'default'           => 0,
			)
		);

		add_settings_field(
			'role_discount_' . $role_name,
			esc_html( $role_info['name'] ),
			'wc_urd_role_discount_field_callback',
			'role_discount_options',
			'role_discount_section',
			array( 'role_name' => $role_name )
		);
	}
}

/**
 * Render discount and free-shipping fields for a role.
 *
 * @param array $args Field arguments.
 */
function wc_urd_role_discount_field_callback( $args ) {
	$role_name     = $args['role_name'];
	$discount      = get_option( 'role_discount_' . $role_name, 0 );
	$free_shipping = (int) get_option( 'role_free_shipping_' . $role_name, 0 );

	printf(
		'<input type="number" step="0.01" min="0" style="width:120px;" name="role_discount_%1$s" value="%2$s" />',
		esc_attr( $role_name ),
		esc_attr( $discount )
	);
	echo ' <span class="description">' . esc_html__( 'Percent discount (products only, before coupons).', 'wc-user-role-discount' ) . '</span>';

	echo '<br /><label style="margin-top:6px;display:inline-block;">';
	printf(
		'<input type="hidden" name="role_free_shipping_%1$s" value="0" />',
		esc_attr( $role_name )
	);
	printf(
		'<input type="checkbox" name="role_free_shipping_%1$s" value="1" %2$s />',
		esc_attr( $role_name ),
		checked( 1, $free_shipping, false )
	);
	echo ' ' . esc_html__( 'Free shipping (overrides shipping cost to $0; package weight and dimensions are unchanged).', 'wc-user-role-discount' );
	echo '</label>';
}
