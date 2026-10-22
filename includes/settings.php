<?php
/**
 * Site Settings: the facts repeated across the site (phones, addresses, email, numbers, social
 * links), described by the theme with elroi_register_settings() and edited in one screen.
 * Read them with elroi_setting( 'key' ), or type {key} in any text field.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability for the client's screens (Editors and Administrators).
 */
function elroi_client_cap() {
	return (string) apply_filters( 'elroi_client_cap', 'edit_others_pages' );
}

add_action(
	'admin_menu',
	function () {
		$cfg   = elroi_settings_config();
		$title = $cfg && $cfg['menu_title'] ? $cfg['menu_title'] : get_bloginfo( 'name' );
		$icon  = $cfg ? $cfg['menu_icon'] : 'dashicons-admin-site-alt3';
		if ( $cfg ) {
			add_menu_page( 'Site Settings', $title, elroi_client_cap(), 'elroi-settings', 'elroi_settings_page', $icon, 3 );
			add_submenu_page( 'elroi-settings', 'Site Settings', 'Site Settings', elroi_client_cap(), 'elroi-settings', 'elroi_settings_page' );
			add_submenu_page( 'elroi-settings', 'How to edit your website', 'How to edit', elroi_client_cap(), 'elroi-guide', 'elroi_guide_page' );
		} else {
			add_menu_page( 'How to edit your website', $title, elroi_client_cap(), 'elroi-guide', 'elroi_guide_page', $icon, 3 );
		}
	}
);

add_action(
	'admin_init',
	function () {
		$cfg = elroi_settings_config();
		if ( ! $cfg ) {
			return;
		}
		register_setting(
			'elroi_settings',
			$cfg['option'],
			array(
				'type'              => 'array',
				'sanitize_callback' => 'elroi_settings_sanitize',
				'default'           => array(),
			)
		);
	}
);

// Editors may save Site Settings (options.php checks this capability).
add_filter( 'option_page_capability_elroi_settings', 'elroi_client_cap' );

/**
 * Clean the posted settings. Empty fields are not stored (they show the default); an emptied
 * clearable field is stored as '-' so it stays empty.
 *
 * @param mixed $in Posted values.
 */
function elroi_settings_sanitize( $in ) {
	$in  = is_array( $in ) ? $in : array();
	$out = array();
	foreach ( elroi_settings_config()['sections'] as $sec ) {
		foreach ( $sec['fields'] as $k => $f ) {
			$v = elroi_build_value( $f, $in[ $k ] ?? null );
			if ( null !== $v ) {
				$out[ $k ] = $v;
			} elseif ( ! empty( $f['clearable'] ) ) {
				$out[ $k ] = '-';
			}
		}
	}
	return $out;
}

/**
 * The settings screen.
 */
function elroi_settings_page() {
	$cfg = elroi_settings_config();
	if ( ! $cfg || ! current_user_can( elroi_client_cap() ) ) {
		return;
	}
	$saved = (array) get_option( $cfg['option'], array() );
	?>
	<div class="wrap elroi elroi-settings">
		<h1>Site Settings</h1>
		<p>Change a detail here once and it updates everywhere on the website, including the business information Google reads. Leave a field empty to go back to the original value.</p>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'elroi_settings' ); ?>
			<?php foreach ( $cfg['sections'] as $sec ) : ?>
				<?php
				$values = array();
				foreach ( $sec['fields'] as $k => $f ) {
					$v            = $saved[ $k ] ?? '';
					$values[ $k ] = '-' === $v ? '' : ( elroi_is_empty( $v ) ? $f['default'] : $v );
				}
				?>
				<h2 class="elroi-h"><?php echo esc_html( $sec['label'] ); ?></h2>
				<?php if ( $sec['description'] ) : ?>
					<p class="description"><?php echo esc_html( $sec['description'] ); ?></p>
				<?php endif; ?>
				<?php
				elroi_render_fields(
					$sec['fields'],
					$cfg['option'],
					$values,
					array(
						'root'  => $values,
						'depth' => 1,
						'post'  => null,
					)
				);
				?>
			<?php endforeach; ?>
			<?php submit_button( 'Save settings' ); ?>
		</form>
	</div>
	<?php
}

// Alt text typed for a settings image is saved on the image too.
add_action(
	'admin_init',
	function () {
		if ( isset( $_POST['option_page'], $_POST['elroi_alt'] ) && 'elroi_settings' === $_POST['option_page'] && check_admin_referer( 'elroi_settings-options' ) ) {
			elroi_save_alts();
		}
	},
	5
);
