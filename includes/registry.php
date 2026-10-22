<?php
/**
 * Registry: the theme tells the plugin which page templates are editable and what their fields are,
 * and what the Site Settings hold. Call these from the theme's functions.php (any time before
 * `init` finishes; `after_setup_theme` is a good place).
 *
 *   elroi_register_template( 'templates/landing.php', array(
 *       'label'  => 'Landing page',
 *       'fields' => array( 'hero' => array( 'type' => 'group', 'fields' => array( ... ) ) ),
 *   ) );
 *
 * Use the key 'front-page' to target the page set as the home page, whatever its template.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['elroi_templates'] = array();
$GLOBALS['elroi_settings']  = null;

/**
 * Make pages using a template editable.
 *
 * @param string $template Template file relative to the theme (as stored in `_wp_page_template`), or 'front-page'.
 * @param array  $args {
 *     @type string   $label        Name shown in the guide, e.g. "Service page".
 *     @type array    $fields       Field definitions (see includes/fields.php).
 *     @type string   $meta_key     Page meta that stores the JSON. Default `_elroi_content`.
 *     @type string   $box_title    Title of the form box. Default "Page content".
 *     @type string   $intro        Extra line at the top of the form.
 *     @type bool     $block_editor Keep the block editor on these pages. Default false.
 *     @type bool     $lock         Lock slug, template, parent, status and deletion for non-admins. Default true.
 * }
 */
function elroi_register_template( $template, $args ) {
	$args   = wp_parse_args(
		$args,
		array(
			'label'        => 'Page',
			'fields'       => array(),
			'meta_key'     => '_elroi_content', // phpcs:ignore WordPress.DB.SlowDBQuery
			'box_title'    => 'Page content',
			'intro'        => '',
			'block_editor' => false,
			'lock'         => true,
		)
	);
	$fields = array();
	foreach ( (array) $args['fields'] as $k => $f ) {
		$fields[ $k ] = elroi_normalize_field( $f, $k );
	}
	$args['fields']                           = $fields;
	$args['template']                         = $template;
	$GLOBALS['elroi_templates'][ $template ] = $args;
}

/**
 * Describe the Site Settings screen.
 *
 * @param array $args {
 *     @type string $option     Option name. Default `elroi_settings`.
 *     @type string $menu_title Top-level wp-admin menu name. Default: the site title.
 *     @type string $menu_icon  Dashicon. Default dashicons-admin-site-alt3.
 *     @type string $support    Who the client contacts for help, shown in the guide.
 *     @type array  $sections   id => array( label, description, fields ). A field may set
 *                              'clearable' => true: emptied, it stays empty (hides that item)
 *                              instead of going back to its default.
 * }
 */
function elroi_register_settings( $args ) {
	$args     = wp_parse_args(
		$args,
		array(
			'option'     => 'elroi_settings',
			'menu_title' => '',
			'menu_icon'  => 'dashicons-admin-site-alt3',
			'support'    => '',
			'sections'   => array(),
		)
	);
	$sections = array();
	foreach ( (array) $args['sections'] as $id => $sec ) {
		$fields = array();
		foreach ( (array) ( $sec['fields'] ?? array() ) as $k => $f ) {
			$fields[ $k ] = elroi_normalize_field( $f, $k );
		}
		$sections[ $id ] = array(
			'label'       => $sec['label'] ?? ucfirst( $id ),
			'description' => $sec['description'] ?? '',
			'fields'      => $fields,
		);
	}
	$args['sections']          = $sections;
	$GLOBALS['elroi_settings'] = $args;
}

/**
 * All registered templates.
 */
function elroi_templates() {
	return $GLOBALS['elroi_templates'];
}

/**
 * Registered settings, or null.
 */
function elroi_settings_config() {
	return $GLOBALS['elroi_settings'];
}

/**
 * The registered template config for a page, or null.
 *
 * @param int|WP_Post|null $post Page.
 */
function elroi_template_for( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return null;
	}
	$all = elroi_templates();
	$tpl = get_post_meta( $post->ID, '_wp_page_template', true );
	if ( $tpl && isset( $all[ $tpl ] ) ) {
		return $all[ $tpl ];
	}
	if ( isset( $all['front-page'] ) && (int) get_option( 'page_on_front' ) === $post->ID ) {
		return $all['front-page'];
	}
	return null;
}

// Every meta key in use keeps revisions, so each Update can be compared and restored.
add_action(
	'init',
	function () {
		$keys = array_unique( array_column( elroi_templates(), 'meta_key' ) );
		foreach ( $keys as $key ) {
			register_post_meta(
				'page',
				$key,
				array(
					'single'            => true,
					'type'              => 'string',
					'show_in_rest'      => false,
					'revisions_enabled' => true,
					'auth_callback'     => fn() => current_user_can( 'edit_pages' ),
				)
			);
		}
	},
	99
);
