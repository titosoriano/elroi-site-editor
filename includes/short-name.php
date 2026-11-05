<?php
/**
 * Short name: a page's title can hold the whole search phrase while menus show a short name.
 *
 * Rank Math checks the focus keyword against the WordPress page title, so the title of a page
 * that should rank for "kitchen remodeling in Morristown, NJ" needs that whole phrase, which is
 * too long for a menu item or a breadcrumb. Every page gets a "Short name" box: when it is filled
 * in, the site shows it wherever the theme or WordPress prints the page title (menus, breadcrumbs,
 * cards, lists of pages, search results); the title itself, and so Rank Math, the browser tab
 * and search engines, keep the full phrase. Empty: the title shows everywhere, as before.
 *
 * Templates that print the page title as the page's <h1> use elroi_full_title(), so the heading
 * keeps the whole phrase. Filters: `elroi_short_names` (false turns the swap off),
 * `elroi_short_name_meta_key` (to use a meta key a site already has).
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/** Meta key of the short name. */
function elroi_short_name_key() {
	return (string) apply_filters( 'elroi_short_name_meta_key', '_elroi_short_name' );
}

/**
 * A page's short name, or '' when it has none.
 *
 * @param int|WP_Post|null $post Page.
 */
function elroi_short_name( $post = null ) {
	$post = get_post( $post );
	return $post ? trim( (string) get_post_meta( $post->ID, elroi_short_name_key(), true ) ) : '';
}

/**
 * The page's full title, ignoring its short name: for the <h1> a template prints from the title.
 *
 * @param int|WP_Post|null $post Page.
 */
function elroi_full_title( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	remove_filter( 'the_title', 'elroi_short_name_title', 10 );
	$title = get_the_title( $post );
	add_filter( 'the_title', 'elroi_short_name_title', 10, 2 );
	return $title;
}

/**
 * The `the_title` filter: pages with a short name show it on the front end.
 *
 * @param string $title Title.
 * @param int    $id    Post ID.
 */
function elroi_short_name_title( $title, $id = 0 ) {
	if ( ! $id || is_admin() || wp_is_serving_rest_request() || is_feed() || ! apply_filters( 'elroi_short_names', true ) || 'page' !== get_post_type( $id ) ) {
		return $title;
	}
	$short = elroi_short_name( $id );
	return '' !== $short ? esc_html( $short ) : $title;
}
add_filter( 'the_title', 'elroi_short_name_title', 10, 2 );

add_action(
	'add_meta_boxes_page',
	function () {
		add_meta_box( 'elroi-short-name', 'Short name', 'elroi_short_name_box', 'page', 'side', 'default' );
	}
);

/**
 * The "Short name" box.
 *
 * @param WP_Post $post Page.
 */
function elroi_short_name_box( $post ) {
	wp_nonce_field( 'elroi_short_name', 'elroi_short_name_nonce' );
	printf(
		'<p><label class="screen-reader-text" for="elroi-short-name">Short name</label><input type="text" id="elroi-short-name" name="elroi_short_name" value="%s" class="widefat" placeholder="%s"></p><p class="description">Shown in menus, breadcrumbs and page lists. Leave it empty to show the title. Keep the full search phrase in the title, e.g. title "Kitchen Remodeling in Morristown, NJ", short name "Kitchen remodeling".</p>',
		esc_attr( elroi_short_name( $post ) ),
		esc_attr( wp_trim_words( $post->post_title, 4, '' ) )
	);
}

add_action(
	'save_post_page',
	function ( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['elroi_short_name_nonce'], $_POST['elroi_short_name'] ) || ! wp_verify_nonce( sanitize_key( $_POST['elroi_short_name_nonce'] ), 'elroi_short_name' ) || ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}
		$short = sanitize_text_field( wp_unslash( $_POST['elroi_short_name'] ) );
		if ( '' === $short ) {
			delete_post_meta( $post_id, elroi_short_name_key() );
		} else {
			update_post_meta( $post_id, elroi_short_name_key(), $short );
		}
	}
);

// The block editor saves meta boxes in a second request; register the key so it is never shown as
// a plain custom field and stays readable through the REST API for editors.
add_action(
	'init',
	function () {
		register_post_meta(
			'page',
			elroi_short_name_key(),
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => fn( $allowed, $key, $post_id ) => current_user_can( 'edit_page', $post_id ),
			)
		);
	}
);
